$.ajaxPrefilter(function(options, original, xhr) {
    if (!options.crossDomain) xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content') || '');
});
$(document).ajaxError(function(event, xhr) { if (xhr.status >= 400) alert('Request failed. Sign in or reload the page and try again.'); });
$(document).ready(function () {
    $('.navShowHide').on('click', function () {
        let nav = $('#sideNavContainer');
        let main = $('#mainSectionContainer');

        if (main.hasClass("leftPadding")) {
            nav.hide();
        } else {
            nav.show();
        }

        main.toggleClass('leftPadding')

    })
})