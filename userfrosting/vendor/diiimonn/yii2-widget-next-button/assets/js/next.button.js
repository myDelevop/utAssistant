(function($){
    $.fn.nextButton = function(o) {
        var options = $.extend(true, {
            ajax: {
                url: '/',
                type: 'GET',
                data: {},
                success: null,
                successError: null,
                error: null
            }
        }, o);

        return this.each(function(i, el) {
            var widget = $(el).closest('.nb__container');
            $(el).unbind('click').on('click', function(e) {
                var btn = $(this)['button'] ? $(this).button('loading') : null;

                options.ajax.data.page = el.page ? el.page : 1;

                $.ajax({
                    url: options.ajax.url,
                    type: options.ajax.type,
                    dataType: 'JSON',
                    data: options.ajax.data,
                    success: function(result) {
                        if (!result.error && result.html && typeof result.page != 'undefined') {
                            $('.nb__items', widget).append(result.html);

                            if (!result.page) {
                                $(el).unbind().remove();
                            } else {
                                el.page = result.page;
                            }

                            if ($.isFunction(options.ajax.success)) {
                                options.ajax.success(result, widget.get(0), el);
                            }
                        } else {
                            if ($.isFunction(options.ajax.successError)) {
                                options.ajax.successError(result, widget.get(0), el);
                            }
                        }
                    },
                    error: function(xhr) {
                        if ($.isFunction(options.ajax.error)) {
                            options.ajax.error(xhr);
                        }
                    }
                }).always(function(){
                    if (btn) {
                        btn.button('reset');
                    }
                });

                e.preventDefault();
                e.stopPropagation();
            });
        });
    };
})(jQuery);