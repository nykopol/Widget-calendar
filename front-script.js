(function($) {
	$.fn.widgetCalendar = function() {
		this.each(function() {

			$('tfoot span', $(this)).parent().one('click', function(){
				$calendar = $(this).parents("div.calendar");
				
				$('.loading img', $calendar).show();
				
				$date = $('span', $(this)).attr('id');
				$.post(ajaxurl, {
					action: 'custom_widget_calendar', 
					date: $date
				}, function($out){
					if($out != '-1'){
						$calendar.replaceWith($out);
						$('.calendar').widgetCalendar();
					}else{
						$('.loading').text('Erreur');
					}
				});
			});
			
		});
		return this;
	};
})(jQuery);

jQuery(function($){
	
	$('.calendar').widgetCalendar();
}); 
