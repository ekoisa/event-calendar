<?php 
echo @$flash_message;
if (!empty($data_read)): 

$num_loop = 0;
$post = $data_read; 
    $num_loop++;
    if($num_loop%2 == 0){
        $class_even = 'list_darken';
    }else{
        $class_even = '';
    }
?>
<div class="calendar_list<?php echo ' '.$class_even; ?>">
		<div class="calendar_list_heading"><?php echo  anchor('calendar/detail/' .$post->id_eventcal .'/'.preg_replace('{[^0-9-a-zA-Z]+}', '-', $post->event_title), stripslashes($post->event_title)); ?></div>
		<p class="calendar_list_info">
			<?php echo lang('calendar_event_date_label');?>: {{helper:date format="<?php echo $dateformat; ?>" timestamp="<?php echo $post->event_date_begin; ?>"}}
			<?php if(!empty($post->event_date_end)): ?>- {{helper:date format="<?php echo $dateformat; ?>" timestamp="<?php echo $post->event_date_end; ?>"}}<?php endif; ?>
		</p>
		<div class="calendar_list_intro">
			<?php echo stripslashes($post->event_content); ?>
		</div>
</div>
<?php endif; ?>
