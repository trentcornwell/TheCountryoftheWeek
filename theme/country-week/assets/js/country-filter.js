/**
 * Client-side search/continent/A-Z filtering for the country archive.
 * The full country list (well under 200 items) is already rendered in
 * the page, so filtering it in the browser avoids extra requests
 * entirely — no fetch, no framework, just DOM show/hide.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-country-filters]');
        var grid = document.querySelector('[data-country-grid]');
        var emptyNotice = document.querySelector('[data-filter-empty]');

        if (!root || !grid) {
            return;
        }

        var searchInput = root.querySelector('[data-filter-search]');
        var continentButtons = root.querySelectorAll('[data-filter-continents] button');
        var alphaButtons = root.querySelectorAll('[data-filter-alpha] button');
        var cards = Array.prototype.slice.call(grid.querySelectorAll('.country-card'));

        var state = { search: '', continent: '', letter: '' };

        function applyFilters() {
            var visibleCount = 0;

            cards.forEach(function (card) {
                var name = (card.getAttribute('data-country-name') || '').toLowerCase();
                var continent = card.getAttribute('data-continent') || '';

                var matchesSearch = state.search === '' || name.indexOf(state.search) !== -1;
                var matchesContinent = state.continent === '' || continent === state.continent;
                var matchesLetter = state.letter === '' || name.charAt(0).toUpperCase() === state.letter;

                var visible = matchesSearch && matchesContinent && matchesLetter;
                card.hidden = !visible;

                if (visible) {
                    visibleCount++;
                }
            });

            if (emptyNotice) {
                emptyNotice.hidden = visibleCount !== 0;
            }
        }

        function setActiveButton(buttons, activeButton) {
            buttons.forEach(function (button) {
                button.classList.toggle('is-active', button === activeButton);
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                state.search = searchInput.value.trim().toLowerCase();
                applyFilters();
            });
        }

        continentButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                state.continent = button.getAttribute('data-continent') || '';
                setActiveButton(continentButtons, button);
                applyFilters();
            });
        });

        alphaButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                state.letter = button.getAttribute('data-letter') || '';
                setActiveButton(alphaButtons, button);
                applyFilters();
            });
        });
    });
})();
