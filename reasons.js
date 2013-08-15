(function() {
    function getRandomInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1) + min);
    }
    var reasons = [
        'Beer and other rejuvination elexirs.',
        'Science and other unproductive activities.',
        'Banks... it always comes down to them you know.',
        'Coca-cola and other toxic substances.',
        'Hook...uhm... I mean, Girls.',
        'Contrubutions to the world economy in the form of spendings for unneeded goods.',
        'Paying evil organizations such as electricity and water suppliers.',
        'Paying for my education and other atrocities against the kind of God creationists believe.',
        'Software licenses for crappy software... and MikroTik.',
        'Fragile hardware like routers, switces and... well, any hardware, really.',
        'Paying for the time-off needed to write semi-jokes about money spending, like the others you can see if you click this link again.'
    ];
    var reasonsGiven = [];
    var donations = document.getElementById('donations');

    var reasonBox = document.createElement('div');
    reasonBox.id = 'reasonBox';
    reasonBox.addEventListener('click', function(e) {
        reasonBox.parentNode.removeChild(reasonBox);
    }, false);

    var reasonAnchor = document.createElement('a');
    reasonAnchor.id = 'reasonAnchor';
    reasonAnchor.href = '#';
    reasonAnchor.innerHTML = 'What will my money be spent on?';
    reasonAnchor.addEventListener('click', function(e) {
        var reason;
        if (reasonsGiven.length === reasons.length) {
            reasonBox.innerHTML = 'What more reason do you need?';
        } else {
            do {
                reason = getRandomInt(0, reasons.length - 1);
            } while (-1 !== reasonsGiven.indexOf(reason));
            reasonBox.innerHTML = reasons[reason];
            reasonsGiven[reasonsGiven.length] = reason;
        }
        donations.appendChild(reasonBox);
    }, false);

    donations.appendChild(reasonAnchor);
})();