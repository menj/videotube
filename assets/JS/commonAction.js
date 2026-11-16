// Page behaviour is attached here through data-action attributes; pages contain no inline script,
// which keeps the Content-Security-Policy strict.
window.VideoTube = window.VideoTube || { actions: {} };

$.ajaxPrefilter(function (options, original, xhr) {
    if (!options.crossDomain) {
        xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content') || '');
    }
});

// Show the server's own explanation when it gives a short plain-text one.
$(document).ajaxError(function (event, xhr) {
    let detail = (xhr.responseText || '').trim();
    if (!detail || detail.length > 200 || detail.charAt(0) === '<' || detail.charAt(0) === '{') {
        detail = 'Request failed. Reload the page and try again.';
    }
    alert(detail);
});

// Actions that change something need a verified account; say so before sending a doomed request.
VideoTube.requireMember = function () {
    const viewer = document.body.getAttribute('data-viewer');
    if (viewer === 'guest') {
        window.location.href = 'signIn.php';
        return false;
    }
    if (viewer === 'unverified') {
        if (confirm('Verify your email address before liking, commenting or subscribing. Open the verification page now?')) {
            window.location.href = 'account.php';
        }
        return false;
    }
    return true;
};

VideoTube.updateCount = function (element, change) {
    const value = (parseInt(element.text(), 10) || 0) + (parseInt(change, 10) || 0);
    element.text(value);
};

$(document).on('click', '[data-action]', function (event) {
    const handler = VideoTube.actions[this.getAttribute('data-action')];
    if (handler) {
        event.preventDefault();
        handler($(this), $(this).data());
    }
});

$(document).on('submit', 'form[data-confirm]', function (event) {
    if (!confirm(this.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});

$(document).on('submit', 'form[data-busy-modal]', function () {
    $(this.getAttribute('data-busy-modal')).modal('show');
});

$(function () {
    $('.navShowHide').on('click', function () {
        $('#sideNavContainer').toggleClass('open');
        $('#mainSectionContainer').toggleClass('leftPadding');
    });
});
