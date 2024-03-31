window.onload = function() {
    var footer = document.getElementById("footer");

    footer.style.display = "none";
}

window.onscroll = function() {
    var footer = document.getElementById("footer");
    var scroll_pos = window.pageYOffset || document.documentElement.scrollTop;

    if (document.getElementById("content").offsetHeight <= window.innerHeight + scroll_pos) {
        footer.style.display = "block";
    } else {
        footer.style.display = "none";
    }

    var menu = document.getElementById("bar_menu");
    var title = document.getElementById("bar_title");

    if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        menu.style.display = "none";
        title.style.display = "none";
    } else {
        menu.style.display = "block";
        title.style.display = "block";
    }
}
