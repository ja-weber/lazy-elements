var lazyElements = {
    loadedElements: [],
    isVisible: function (elm) {
        var vpH = $(window).height(), // Viewport Height.
            st = $(window).scrollTop(), // Scroll Top
            y = $(elm).offset().top,
            elementHeight = $(elm).height();
        return ((y < (vpH + st)) && (y > (st - elementHeight)));
    },
    startWatching: function (contentId, ajaxParams, onlyIfVisible) {
        if (!onlyIfVisible || lazyElements.isVisible(jQuery("#ajax-placeholder-" + contentId))) {
            lazyElements.loadElement(contentId, ajaxParams);
        }

        jQuery(document).scroll(function () {

            if (!onlyIfVisible) {
                //should be loaded already.
                return;
            }

            let placeholder = jQuery("#ajax-placeholder-" + contentId);
            if(placeholder.length === 0){
                return ;
            }
            if (!lazyElements.isVisible(placeholder)) {
                return;
            }

            lazyElements.loadElement(contentId, ajaxParams);
        });
    },
    loadElement: function (contentId, ajaxParams) {
        let placeholder = jQuery('#ajax-placeholder-' + contentId);
        if (placeholder.hasClass("loaded")) {
            return;
        }
        placeholder.addClass("loaded");

        wp.ajax.post('lazy_element_action', ajaxParams).done(function (repsonse) {
            placeholder.html(repsonse);
        }).fail(function (repsonse) {
            console.error(repsonse, 'Error in the ajax wrapper.')
        })
    }
}
