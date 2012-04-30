jQuery.fn.center = function () {
    this.css("position", "absolute");
    this.css("top", (( $(window).height() - this.outerHeight() ) / 2 ) + "px");
    this.css("left", (( $(window).width() - this.outerWidth() ) / 2 ) + "px");
    return this;
}

/*
show a message with a wait image
several asynchronus calls can be made with different messages
*/
var loaderImg = function($)
{
    var aLoading = new Array(),
        _removeLoading = function(id) {
            for (var j=0; j < aLoading.length; j++) {
                if (aLoading[j].id == id) {
                    if (aLoading[j].onHide) {
                        aLoading[j].onHide();
                    }
                    aLoading.splice(j, 1);
                }
            }
        },
        _show = function(id,title,callback) {
            aLoading.push({ id: id, title: title, onHide: callback});
            $("#loader_img_title").append("<div class='" + id + "'>" + title + "</div>");
            if (aLoading.length == 1) {
                $("#loader_img").css("display", "block");
            }
            $("#loader_img_title").center();
        },
        _hide = function(id) {
            _removeLoading(id);
            if (aLoading.length == 0) {
                $("#loader_img").css("display", "none");
                $("#loader_img_title div").remove();
            } else {
                $("#loader_img_title ." + id).remove();
                $("#loader_img_title").center();
            }
        };
    
    return {
        show: _show,
        hide: _hide
    };

}(jQuery); // end of function loaderImg