// Uses CommonJS, AMD or browser globals to create a jQuery plugin.
;
(function (factory) {
    if (typeof define === "function" && define.amd) {
        // AMD. Register as an anonymous module.
        define(["jquery"], factory);
    }
    else if (typeof module === "object" && module.exports) {
        // Node/CommonJS
        module.exports = function (root, jQuery) {
            if (jQuery === undefined) {
                // require("jQuery") returns a factory that requires window to
                // build a jQuery instance, we normalize how we use modules
                // that require this pattern but the window provided is a noop
                // if it's defined (how jquery works)
                if (typeof window !== "undefined") {
                    jQuery = require("jquery");
                }
                else {
                    jQuery = require("jquery")(root);
                }
            }
            factory(jQuery);
            return jQuery;
        };
    }
    else {
        // Browser globals
        factory(jQuery);
    }
}(function ($, window, document, undefined) {
  
    /**
   * Options for initializing gSpinner
   *
   * @param   {any} options
   * @returns
   */
    $.fn.gSpinner = function (options) {
        // By default, the spinner is loading and at full scale
        var defaults = {
            loading: true,
            scale: 1
        };
        var $this = $(this);
        // If hide is passed into the gSpinner method, set loading to false
        if (options === "hide") {
            options = {
                loading: false
            };
        }
        var config = $.extend({}, defaults, options);
        return this.each(
            function () {
                if (config.loading === true) {
                    var spinner = $("<div class='loading' id='g-spinner'>")
                    .append(
                        ["<div class='circle c1'></div>",
                        "<div class='circle c2'></div>",
                        "<div class='circle c3'></div>",
                        "<div class='circle c4'></div>"
                        ].join("")
                    ).css(
                        {
                            "zoom": config.scale
                        }
                    );
                    $this
                      .empty()
                      .append(spinner);
                }
                else {
                    $this
                    .removeClass("loading")
                    .empty()
                }
            }
        )
    }
}));
