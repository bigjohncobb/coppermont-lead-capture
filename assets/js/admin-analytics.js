(function () {
	'use strict';

	const cards = document.querySelectorAll('.cmlc-kpi-card p');
	cards.forEach(function (card) {
		card.setAttribute('title', 'Filtered analytics metric');
	});
})();
