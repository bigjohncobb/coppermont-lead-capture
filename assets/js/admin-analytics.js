(function () {
	const startInput = document.querySelector('input[name="start_date"]');
	const endInput = document.querySelector('input[name="end_date"]');
	if (!startInput || !endInput) {
		return;
	}

	startInput.addEventListener('change', function () {
		if (endInput.value && startInput.value > endInput.value) {
			endInput.value = startInput.value;
		}
	});
})();
