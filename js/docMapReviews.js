/**
 * @file plugins/generic/docMapReviews/js/docMapReviews.js

 * @package plugins.generic.docMapReviews
 *
 */

function more(id) {
    var div = "div-" + id;
    var btn = "btn-" + id;
    var less = "less-" + id;
    document.getElementById(div).style.display = "block";
    document.getElementById(btn).style.display = "none";
    document.getElementById(less).style.display = "block";
}

function less(id) {
    var div = "div-" + id;
    var btn = "btn-" + id;
    var less = "less-" + id;
    document.getElementById(div).style.display = "none";
    document.getElementById(btn).style.display = "block";
    document.getElementById(less).style.display = "none";
}