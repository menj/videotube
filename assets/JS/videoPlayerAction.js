(function () {
    const icons = 'assets/images/icons/';

    function vote(endpoint, own, other, ownIcon, otherIcon, ownKey) {
        return function (button, data) {
            if (!VideoTube.requireMember()) {
                return;
            }
            $.post('ajax/' + endpoint, { videoId: data.videoId }).done(function (response) {
                const result = JSON.parse(response);
                const ownButton = button;
                const otherButton = button.siblings(other);
                const likeButton = own === '.likeButton' ? ownButton : otherButton;
                const dislikeButton = own === '.likeButton' ? otherButton : ownButton;

                VideoTube.updateCount(likeButton.find('.text'), result.likes);
                VideoTube.updateCount(dislikeButton.find('.text'), result.disLikes);

                const active = result[ownKey] > 0;
                ownButton.toggleClass('active', active);
                ownButton.find('img:first').attr('src', icons + ownIcon + (active ? '-active' : '') + '.png');
                otherButton.removeClass('active');
                otherButton.find('img:first').attr('src', icons + otherIcon + '.png');
            });
        };
    }

    VideoTube.actions.likeVideo = vote('likeVideo.php', '.likeButton', '.disLikeButton', 'thumb-up', 'thumb-down', 'likes');
    VideoTube.actions.dislikeVideo = vote('disLikeVideo.php', '.disLikeButton', '.likeButton', 'thumb-down', 'thumb-up', 'disLikes');
})();
