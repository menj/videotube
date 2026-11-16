VideoTube.actions.subscribe = function (button, data) {
    if (!VideoTube.requireMember()) {
        return;
    }
    $.post('ajax/subscribe.php', { userTo: data.userTo }).done(function (count) {
        button.toggleClass('subscribe unsubscribe');
        const label = button.hasClass('subscribe') ? 'SUBSCRIBE' : 'SUBSCRIBED';
        button.find('.text').text(label + ' ' + count);
    });
};
