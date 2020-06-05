/*
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

$(document).ready(
    function () {

        var input = $("input");
    
        // focus search input `onkeypress`
        $(document).keypress(
            function (e) {

                if (!input.is(":focus")) {

                    var v = String.fromCharCode(e.which);
                    if (v.match(/[a-z0-9]/i)) {
                        input.val(input.val() + v);
                    }

                    input.focus();
                }

            }
        );
    }
);
