<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Calendar_m extends MY_Model
{
        
	function get_events($time, $is_thumb = false, $get_repeated = false, $thismonth = false){
		
		$today = date("Y/n/j", time());
		$current_month = date("n", $time);
		$current_year = date("Y", $time);
		$current_month_text = date("F Y", $time);
		$total_days_of_current_month = date("t", $time);
		
		$events = array();
		
		$content = "";
		if(!$is_thumb){
			$content = '`event_content`,';
		}
		
		
        $this->db->select(" `id_eventcal`, `user_id`, ".$content." `event_title`, `event_date_begin`, `event_date_end`, `event_repeat`, `event_repeat_prm` ");
        $this->db->select(" DATE_FORMAT(`event_date_begin`,'%d') AS day", FALSE);
        $this->db->where('event_repeat', 0);
        $str_where = 'event_date_begin BETWEEN \''.$current_year.'-'.$current_month.'-01\'  AND \''.$current_year.'-'.$current_month.'-'.$total_days_of_current_month.'\' ';
		$this->db->where($str_where);
		
		//$this->db->where(" event_date_begin >=",  $current_year."/".$current_month."/01");
		//$this->db->where(" event_date_begin <",   $current_year."/".$current_month."/".$total_days_of_current_month);
		//$strquery1 = $this->db->get_compiled_select($this->db->dbprefix('eventcal'));
		$query = $this->db->get($this->db->dbprefix('eventcal'));
        
        if($get_repeated){
            $this->db->select(" `id_eventcal`, `user_id`, ".$content." `event_title`, `event_date_begin`, `event_date_end`, `event_repeat`, `event_repeat_prm` ");
            $this->db->select(" DATE_FORMAT(`event_date_begin`,'%d') AS day", FALSE);
            $this->db->where('event_repeat', 1);
            
            $query2 = $this->db->get($this->db->dbprefix('eventcal'));
            $rquery1 = $query->result();
            $rquery2 = $query2->result();
            
            $rquery = (object)array_merge((array)$rquery1, (array)$rquery2);
        }else{
            $rquery = $query->result();
        }
        
		$curtime = time();
		
		foreach ($rquery as $row_event)
		{
			if($row_event->event_repeat == 1){
				$prm = @json_decode($row_event->event_repeat_prm);
				if(isset($prm->type) and $prm->type == 0){
                    $event_time = strtotime(date('Y-m-d').' '.$prm->time.':00:00');
                    if($curtime > $event_time){
                        $row_event->event_date_begin = date('Y-m-d H:i:s', strtotime(date('Y-m-d').' '.$prm->time.':00:00 +1 day'));
                    }else{
                        $row_event->event_date_begin = date('Y-m-d H:i:s', $event_time);
                    }
					$curday = date('d', strtotime($row_event->event_date_begin));
				}elseif(isset($prm->type) and $prm->type == 1){
					for($k = 0; $k < 7; $k++){
						$looptime = strtotime(date('Y-m-d').' +'.$k.' day');
						
						if(date('w', $looptime) == $prm->day){
							$event_time = strtotime(date('Y-m-d').' '.$prm->time.':00:00'.' +'.$k.' day');
							if($curtime <= $event_time){
								$row_event->event_date_begin = date('Y-m-d H:i:s', $event_time);
								break;
							}else{
								$row_event->event_date_begin = date('Y-m-d H:i:s', strtotime(date('Y-m-d').' '.$prm->time.':00:00'.' +'.($k+7).' day'));
								break;
							}
						}
					}
					$curday = date('d', strtotime($row_event->event_date_begin));
				}elseif(isset($prm->type) and $prm->type == 2){
					$event_time = strtotime(date('Y-m-').$prm->date.' '.$prm->time.':00:00');
					$lasmonthtime = strtotime(date('Y-m-').'01 23:00:00 +1 month -1 day');
					if($event_time <= $lasmonthtime){
						$row_event->event_date_begin = date('Y-m-d H:i:s', $event_time);
					}elseif(!$thismonth){
						$row_event->event_date_begin = date('Y-m-d H:i:s', strtotime(date('Y-m-').$prm->date.' '.$prm->time.':00:00 +1 month'));
					}
				
					$curday = date('d', strtotime($row_event->event_date_begin));
				}
				
				$events[intval($curday)][] = $row_event;
			}else{
				$events[intval($row_event->day)][] = $row_event;
			}
		}
		$query->free_result();
		return $events;						
	}
	
	
	function add_event($data = array())
        {
            $result=$this->db->insert($this->db->dbprefix('eventcal'),$data);

            //check if the insertion is ok
            if($result)
                return $this->db->insert_id();
            else
                return false;
		
	}
	
	function edit_event($id, $data = array())
        {
            $this->db->where('id_eventcal', $id);
            $result=$this->db->update($this->db->dbprefix('eventcal'),$data);

            //check if the insertion is ok
            if($result)
                return true;
            else
                return false;
		
	}
    
	function get_event_by_id($id = 0)
    {
        if($id == 0){
            return false;
        }

		$this->db->where('id_eventcal', $id);
		$query = $this->db->get($this->db->dbprefix('eventcal'));
		return $query->row();
	}
	
	function count_event_by($prm = array())
    {
        $this->_table = $this->db->dbprefix('eventcal');
        $this->primary_key = 'id_eventcal';
        
        $this->db->select('count(id_eventcal) as jml');
        if(!empty($prm['title'])){
            $prm_title = str_replace('%', ' ', $prm['title']);
            $prm_title = explode(' ', $prm_title);
            
            $counter = 0;
			foreach ($prm_title as $val)
			{
                if($counter == 0){
                    $this->db->like('event_title', $val);
                }else{
                    $this->db->or_like('event_title', $val);
                }
                
                $counter++;
            }
        }
        if(!empty($prm['date'])){
            $this->db->where(" DATE(event_date_begin)", "'".$prm['date']."'", false);
            //$this->db->where(" DATE(event_date_end) >=", $prm['date']);
        }
        if(!empty($prm['date_start'])){
            if(empty($prm['date_end'])){
                $this->db->where(" DATE(event_date_begin) >= '".$prm['date_start']."' ", "", false);
            }else{
                $this->db->where(" DATE(event_date_begin) BETWEEN '".$prm['date_start']."' AND '".$prm['date_end']."' ", "", false);
            }
        }
		$this->db->where('event_repeat', 0);
        
		$query = $this->db->get($this->db->dbprefix('eventcal'));
        $hsl1 = $query->row();
		
		if(!empty($prm['getrepeat']) and $prm['getrepeat'] == 1){
			$this->db->select('count(id_eventcal) as jml');
			if(!empty($prm['title'])){
				$prm_title = str_replace('%', ' ', $prm['title']);
				$prm_title = explode(' ', $prm_title);
				
				$counter = 0;
				foreach ($prm_title as $val)
				{
					if($counter == 0){
						$this->db->like('event_title', $val);
					}else{
						$this->db->or_like('event_title', $val);
					}
					
					$counter++;
				}
			}
			
			$this->db->where('event_repeat', 1);
			
			$query = $this->db->get($this->db->dbprefix('eventcal'));
			$hsl2 = $query->row();
			$hsl = ($hsl1->jml+$hsl2->jml);
		}
		$hsl = ($hsl1->jml);
		return $hsl;
	}
    
	function list_event_by($prm = array())
    {
        $this->_table = $this->db->dbprefix('eventcal');
        $this->primary_key = 'id_eventcal';
        
        $this->db->select($this->db->dbprefix('eventcal').".*, ".$this->db->dbprefix('profiles').".display_name ");
        $this->db->join($this->db->dbprefix('profiles'), $this->db->dbprefix('profiles').".user_id = ".$this->db->dbprefix('eventcal').".user_id ", 'left');
        
        if(!empty($prm['title'])){
            $prm_title = str_replace('%', ' ', $prm['title']);
            $prm_title = explode(' ', $prm_title);
            
            $counter = 0;
			foreach ($prm_title as $val)
			{
                if($counter == 0){
                    $this->db->like('event_title', $val);
                }else{
                    $this->db->or_like('event_title', $val);
                }
                
                $counter++;
            }
        }
        if(!empty($prm['date'])){
            $this->db->where(" DATE(event_date_begin)", "'".$prm['date']."'", false);
            //$this->db->where(" DATE(event_date_end) >=", $prm['date']);
        }
        
        
        if(!empty($prm['date_start'])){
            if(empty($prm['date_end'])){
                $this->db->where(" DATE(event_date_begin) >= '".$prm['date_start']."' ", "", false);
            }else{
                $this->db->where(" DATE(event_date_begin) BETWEEN '".$prm['date_start']."' AND '".$prm['date_end']."' ", "", false);
            }
        }
        
		$this->db->where('event_repeat', 0);
		
        if(!empty($prm['order'])){
            $this->db->order_by($prm['order']);
        }
        
        // Limit the results based on 1 number or 2 (2nd is offset)
		if (!empty($prm['limit']) && is_array($prm['limit']))
			$this->db->limit($prm['limit'][0], $prm['limit'][1]);
		elseif (!empty($prm['limit']))
			$this->db->limit($prm['limit']);
        
		$query1 = $this->db->get($this->db->dbprefix('eventcal'));
		
		
        if(!empty($prm['getrepeat']) and $prm['getrepeat'] == 1){
			$this->db->select($this->db->dbprefix('eventcal').".*, ".$this->db->dbprefix('profiles').".display_name ");
			$this->db->join($this->db->dbprefix('profiles'), $this->db->dbprefix('profiles').".user_id = ".$this->db->dbprefix('eventcal').".user_id ", 'left');
			
			if(!empty($prm['title'])){
				$prm_title = str_replace('%', ' ', $prm['title']);
				$prm_title = explode(' ', $prm_title);
				
				$counter = 0;
				foreach ($prm_title as $val)
				{
					if($counter == 0){
						$this->db->like('event_title', $val);
					}else{
						$this->db->or_like('event_title', $val);
					}
					
					$counter++;
				}
			}
			
			$this->db->where('event_repeat', 1);
			
			if(!empty($prm['order'])){
				$this->db->order_by($prm['order']);
			}
			
			$query2 = $this->db->get($this->db->dbprefix('eventcal'));
			$rquery1 = $query1->result();
            $rquery2 = $query2->result();
			
			$curtime = time();
			foreach($rquery2 as $key => $row_event){
				$prm = @json_decode($row_event->event_repeat_prm);
				if(isset($prm->type) and $prm->type == 0){
                    $event_time = strtotime(date('Y-m-d').' '.$prm->time.':00:00');
                    if($curtime > $event_time){
                        $row_event->event_date_begin = date('Y-m-d H:i:s', strtotime(date('Y-m-d').' '.$prm->time.':00:00 +1 day'));
                    }else{
                        $row_event->event_date_begin = date('Y-m-d H:i:s', $event_time);
                    }
				}elseif(isset($prm->type) and $prm->type == 1){
					for($k = 0; $k < 7; $k++){
						$looptime = strtotime(date('Y-m-d').' +'.$k.' day');
						
						if(date('w', $looptime) == $prm->day){
							$event_time = strtotime(date('Y-m-d').' '.$prm->time.':00:00'.' +'.$k.' day');
							if($curtime <= $event_time){
								$row_event->event_date_begin = date('Y-m-d H:i:s', $event_time);
								break;
							}else{
								$row_event->event_date_begin = date('Y-m-d H:i:s', strtotime(date('Y-m-d').' '.$prm->time.':00:00'.' +'.($k+7).' day'));
								break;
							}
						}
					}
				}elseif(isset($prm->type) and $prm->type == 2){
					$event_time = strtotime(date('Y-m-').$prm->date.' '.$prm->time.':00:00');
					$lasmonthtime = strtotime(date('Y-m-').'01 23:00:00 +1 month -1 day');
					if($event_time <= $lasmonthtime){
						$row_event->event_date_begin = date('Y-m-d H:i:s', $event_time);
					}elseif(!$thismonth){
						$row_event->event_date_begin = date('Y-m-d H:i:s', strtotime(date('Y-m-').$prm->date.' '.$prm->time.':00:00 +1 month'));
					}
				
				}
				
				$rquery2[$key] = $row_event;
			}
			
			$rquery = (object)array_merge((array)$rquery2, (array)$rquery1);
		}else{
            $rquery = $query1->result();
        }
		
		return $rquery;
	}
    
	function updateEvent(){
		
		$data = array(
               'event_date_begin' => $_POST['date'],
               'event_title' => $_POST['eventTitle'],
               'event_content' => $_POST['eventContent']
            );
		$this->db->where('id', $_POST['id']);
		$this->db->update($this->db->dbprefix('eventcal'), $data); 
	}
	
	
	function deleteEvent($id){
		return $this->db->delete($this->db->dbprefix('eventcal'), array('id_eventcal' => $id)); 
	}
	
// end of Model/calendar_m.php
}
