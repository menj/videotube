(function () {
    const icons = 'assets/images/icons/';

    function adjustCommentCount(change) {
        const counter = $('.commentSection .commentCount');
        const total = Math.max(0, (parseInt(counter.text(), 10) || 0) + change);
        counter.text(total + ' comments');
    }

    VideoTube.actions.postComment = function (button, data) {
        if (!VideoTube.requireMember()) {
            return;
        }
        const textarea = button.siblings('textarea');
        const commentText = textarea.val();
        if (!commentText || !commentText.trim()) {
            alert("You can't post an empty comment");
            return;
        }
        button.prop('disabled', true);
        $.post('ajax/postComment.php', { commentText: commentText, videoId: data.videoId, responseTo: data.replyTo })
            .done(function (comment) {
                if (textarea.val() === commentText) {
                    textarea.val('');
                }
                adjustCommentCount(1);
                if (!data.replyTo) {
                    $('.commentSection .comments').prepend(comment);
                    return;
                }
                // A reply written under another reply belongs to the enclosing thread.
                const item = button.closest('.itemContainer');
                const thread = item.parent().closest('.repliesSection');
                const target = thread.length ? thread : item.children('.repliesSection');
                item.children('.commentForm').addClass('hidden');
                if (target.hasClass('viewReplies')) {
                    target.trigger('click'); // Collapsed thread: load it, including the new reply.
                } else {
                    target.append(comment);
                }
            })
            .always(function () {
                button.prop('disabled', false);
            });
    };

    VideoTube.actions.toggleReply = function (button) {
        if (!VideoTube.requireMember()) {
            return;
        }
        button.closest('.itemContainer').children('.commentForm').toggleClass('hidden');
    };

    function vote(endpoint, ownIcon, otherIcon, other, direction) {
        return function (button, data) {
            if (!VideoTube.requireMember()) {
                return;
            }
            $.post('ajax/' + endpoint, { commentId: data.commentId, videoId: data.videoId }).done(function (change) {
                change = parseInt(change, 10) || 0;
                const otherButton = button.siblings(other);
                const count = button.siblings('.likesCount');
                const score = (parseInt(count.text(), 10) || 0) + change;
                count.text(score === 0 ? '' : score);

                // The score moves in the vote's own direction when it is set, and back when it is withdrawn.
                const active = change * direction > 0;
                button.toggleClass('active', active);
                button.find('img:first').attr('src', icons + ownIcon + (active ? '-active' : '') + '.png');
                otherButton.removeClass('active');
                otherButton.find('img:first').attr('src', icons + otherIcon + '.png');
            });
        };
    }

    VideoTube.actions.likeComment = vote('likeComment.php', 'thumb-up', 'thumb-down', '.disLikeButton', 1);
    VideoTube.actions.dislikeComment = vote('disLikeComment.php', 'thumb-down', 'thumb-up', '.likeButton', -1);

    VideoTube.actions.getReplies = function (link, data) {
        $.post('ajax/getCommentReplies.php', { commentId: data.commentId, videoId: data.videoId }).done(function (comments) {
            link.replaceWith($('<div>').addClass('repliesSection').append(comments));
        });
    };

    VideoTube.actions.loadComments = function (button, data) {
        button.prop('disabled', true);
        $.post('ajax/getComments.php', { videoId: data.videoId, before: data.before })
            .done(function (comments) {
                button.replaceWith(comments);
            })
            .fail(function () {
                button.prop('disabled', false);
            });
    };

    VideoTube.actions.deleteComment = function (button, data) {
        if (!confirm('Delete this comment and its replies?')) {
            return;
        }
        $.post('ajax/deleteComment.php', { commentId: data.commentId, videoId: data.videoId }).done(function () {
            const item = button.closest('.itemContainer');
            adjustCommentCount(-1 - item.find('.itemContainer').length);
            item.remove();
        });
    };
})();
