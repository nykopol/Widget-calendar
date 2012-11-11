jQuery(function($) {
	$('.sidebar-item-content .ui-datepicker-prev').click(function(e){
		e.preventDefault();
		
		$widget = $(this).parents('.sidebar-item');
		$widget_id = $widget.attr('id');
		
		
		$datas = {};
		$datas.action = 'load-widget-calendar';
		$datas.widget = $widget_id;
		$.post($(this).attr('href'), $datas, function(html){alert(html);
			$widget.replaceWith(html);
		});
	});
});
