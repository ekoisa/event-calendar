<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Calendar Plugin
 *
 * Create lists of event calendar
 *
 * @package		PyroCMS v2
 * @author		Eko Muhammad Isa
 *
 */
class Plugin_Calendar extends Plugin
{
	/**
	 * Event Calendar List
	 *
	 * Creates a list of event
	 *
	 * Usage:
	 * {{ calendar:read period-start="7" period-end="7" order-by="title" limit="5" }}
	 *		<a href="{{ url }}" class="">{{ title }}</a><br/>
	 * {{ /calendar:read }}
	 *
	 *	period-start is many days before today
	 *	period-end is many days after today
	 * order-by choice between title and date_start
	 * 
	 * @param	array
	 * @return	array
	 */
	public function read()
	{
		$limit		= $this->attribute('limit', 10);
		$period_start	= $this->attribute('period-start');
		$period_start	= ($period_start) ? $period_start : 3;
        $period_end	= $this->attribute('period-end');
        $repeated	= $this->attribute('repeated');
		$period_end	= ($period_end) ? $period_end : 7;
		$repeated	= ($repeated) ? $repeated : 'no';
		$order_by 	= $this->attribute('order-by', 'event_date_begin DESC');

		$tbl = $this->db->dbprefix('eventcal');
		$tjoin = $this->db->dbprefix('profiles');
        
		
		
        $vname = 'modcalendar_plugin_read';
        if(!$posts = $this->pyrocache->get('calendar_cache'.DIRECTORY_SEPARATOR.'cache_'.$vname))
        {
            
            $this->db->where(' DATE('.$tbl.'.event_date_begin) BETWEEN DATE_ADD(CURDATE(), INTERVAL -'.$period_start.' DAY)  AND DATE_ADD(CURDATE(), INTERVAL '.$period_end.' DAY)', '', false);
		
            if ($order_by and $order_by != 'title')
            {
                $this->db->order_by($order_by);
            }else{
                $this->db->order_by($tbl.'.event_title ASC');
            }
            
            $posts1 = $this->db
			->select($tbl.'.id_eventcal, '.$tbl.'.event_title as title, '.$tbl.'.event_date_begin as date_start, '.$tbl.'.event_date_end as date_end, '.$tjoin.'.display_name as user_post')
			->join($tjoin, $tbl.'.user_id = '.$tjoin.'.user_id', 'left')
			->where('event_repeat', 0)
			->limit($limit)
			->get($tbl)
			->result();
			
			if($repeated == 'yes'){
				$posts2 = $this->db
				->select($tbl.'.id_eventcal, '.$tbl.'.event_title as title, '.$tbl.'.event_date_begin as date_start, '.$tbl.'.event_date_end as date_end, '.$tjoin.'.display_name as user_post, '.$tbl.'.event_repeat_prm')
				->join($tjoin, $tbl.'.user_id = '.$tjoin.'.user_id', 'left')
				->where('event_repeat', 1)
				->get($tbl)
				->result();
				
				$curtime = time();
				$posts2 = (array)$posts2;
				foreach($posts2 as $key => $row_event){
					$prm = @json_decode($row_event->event_repeat_prm);
					if(isset($prm->type) and $prm->type == 0){
						$event_time = strtotime(date('Y-m-d').' '.$prm->time.':00:00');
						if($curtime > $event_time){
							$row_event->date_start = date('Y-m-d H:i:s', strtotime(date('Y-m-d').' '.$prm->time.':00:00 +1 day'));
						}else{
							$row_event->date_start = date('Y-m-d H:i:s', $event_time);
						}
					}elseif(isset($prm->type) and $prm->type == 1){
						for($k = 0; $k < 7; $k++){
							$looptime = strtotime(date('Y-m-d').' +'.$k.' day');
							
							if(date('w', $looptime) == $prm->day){
								$event_time = strtotime(date('Y-m-d').' '.$prm->time.':00:00'.' +'.$k.' day');
								if($curtime <= $event_time){
									$row_event->date_start = date('Y-m-d H:i:s', $event_time);
									break;
								}else{
									$row_event->date_start = date('Y-m-d H:i:s', strtotime(date('Y-m-d').' '.$prm->time.':00:00'.' +'.($k+7).' day'));
									break;
								}
							}
						}
					}elseif(isset($prm->type) and $prm->type == 2){
						$event_time = strtotime(date('Y-m-').$prm->date.' '.$prm->time.':00:00');
						$lasmonthtime = strtotime(date('Y-m-').'01 23:00:00 +1 month -1 day');
						if($event_time <= $lasmonthtime){
							$row_event->date_start = date('Y-m-d H:i:s', $event_time);
						}elseif(!$thismonth){
							$row_event->date_start = date('Y-m-d H:i:s', strtotime(date('Y-m-').$prm->date.' '.$prm->time.':00:00 +1 month'));
						}
					
					}
					
					$posts2[$key] = $row_event;
				}
				
				$posts = array_merge($posts2, (array)$posts1);
			}else{
				$posts = $posts1;
			}
			
			
            $this->pyrocache->write($posts, 'calendar_cache'.DIRECTORY_SEPARATOR.'cache_'.$vname, 14400);
        }
		
		
        $vname = 'modcalendar_calendar_dateformat';
        if(!$v3 = $this->pyrocache->get('calendar_cache'.DIRECTORY_SEPARATOR.'get_by_'.$vname))
        {
			$this->load->model('variables/variables_m');
            $v3 = $this->variables_m->get_by('name', $vname);
            $this->pyrocache->write($v3, 'calendar_cache'.DIRECTORY_SEPARATOR.'get_by_'.$vname);
        }
		$dateformat = empty($v3->data) ? 'M d, Y H:i' : $v3->data;
		
		
		foreach ($posts as &$post)
		{
			$post->url = htmlentities(site_url('calendar/detail/' .$post->id_eventcal .'/'.preg_replace('{[^0-9-a-zA-Z]+}', '-', $post->title), stripslashes($post->title)), ENT_QUOTES);
			$post->f_date_start = date($dateformat, strtotime($post->date_start));
			$post->f_date_end = date($dateformat, strtotime($post->date_end));
		}
		
		return $posts;
	}
    
	/**
	 * Event Calendar Count
	 *
	 * Creates a count of event
	 *
	 * Usage:
	 * {{ calendar:count period-start="7" period-end="7" }}
	 *
	 *	period-start is many days before today
	 *	period-end is many days after today
	 * 
	 * @param	array
	 * @return	count of calendar event
	 */
	public function count()
	{
		$period_start	= $this->attribute('period-start');
        $period_start	= ($period_start) ? $period_start : 3;
		$period_end	= $this->attribute('period-end');
		$repeated	= $this->attribute('repeated');
        $period_end	= ($period_end) ? $period_end : 7;
		$repeated	= ($repeated) ? $repeated : 'no';

        $tbl = $this->db->dbprefix('eventcal');
        
		
		$vname = 'modcalendar_plugin_count';
        if(!$posts = $this->pyrocache->get('calendar_cache'.DIRECTORY_SEPARATOR.'cache_'.$vname))
        { 
            
            $this->db->where(' DATE('.$tbl.'.event_date_begin) BETWEEN DATE_ADD(CURDATE(), INTERVAL -'.$period_start.' DAY)  AND DATE_ADD(CURDATE(), INTERVAL '.$period_end.' DAY)', '', false);
        
            $posts1 = $this->db
			->select('count('.$tbl.'.id_eventcal) as count_calendar')
			->where('event_repeat', 0)
			->get($tbl)
			->row();
			
			
			if($repeated == 'yes'){
				$posts2 = $this->db
				->select('count('.$tbl.'.id_eventcal) as count_calendar')
				->where('event_repeat', 1)
				->get($tbl)
				->row();
				
				$posts = $posts2->count_calendar + $posts1->count_calendar;
			}else{
				$posts = $posts1->count_calendar;
			}
			
            $this->pyrocache->write($posts, 'calendar_cache'.DIRECTORY_SEPARATOR.'cache_'.$vname, 14400);
        }
		
		
		return $posts;
	}
}

/* End of file plugin.php */
