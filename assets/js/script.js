document.addEventListener('DOMContentLoaded', () => {
    console.log("Fetching Wordle data from API...");

    // Determine the API path dynamically
    const apiUrl = typeof wordleHintData !== 'undefined' ? wordleHintData.apiUrl + 'today' : '/wp-json/wordle/v1/today';

    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('API response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            console.log("API DATA LOADED:", data);

            // Data mapping from API response
            if (data && data.word) {
                populateUI(data);
            }
        })
        .catch(error => {
            console.error("API FETCH ERROR:", error);
        });

    function populateUI(data) {
        const $container = jQuery('.wordle-hint-container');
        if (!$container.length) return;

        // Update date element with data.date
        const dateObj = new Date(data.date + 'T00:00:00');
        const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        $container.find('.wh-date').text(formattedDate);

        // Update number with data.number
        $container.find('.wh-puzzle-num').text('#' + data.number);

        // Update vowels with data.vowels
        $container.find('.wh-stat-item:contains("Vowels") .wh-highlight').text(data.vowels);

        // Update starting letter with data.starts_with
        $container.find('.wh-stat-item:contains("Starts With") .wh-highlight').text(data.starts_with);

        // Hints
        $container.find('.wh-hint-card[data-hint="1"] .wh-hint-text').text(data.hints.vague);
        $container.find('.wh-hint-card[data-hint="2"] .wh-hint-text').text(data.hints.category);
        $container.find('.wh-hint-card[data-hint="3"] .wh-hint-text').text(data.hints.specific);
        $container.find('.wh-hint-card[data-hint="4"] .wh-hint-text').text(data.hints.final);

        // Update data-word attribute for reveal logic
        const $grid = $container.find('#wh-answer-grid');
        $grid.attr('data-word', data.word.toUpperCase());

        // Populate Answer Tiles
        const letters = data.word.toUpperCase().split('');
        const tiles = document.querySelectorAll('.wh-box-back');

        letters.forEach((letter, index) => {
            if (tiles[index]) {
                tiles[index].innerText = letter;
            }
        });

        // Initialize or Update Game Logic
        initGameLogic($container);
    }

    function initGameLogic($container) {
        // --- Theme Toggle Logic ---
        const $themeToggle = jQuery('#wh-theme-toggle');
        const $body = jQuery('body');
        
        $themeToggle.off('click').on('click', function() {
            $body.toggleClass('dark-mode');
            const theme = $body.hasClass('dark-mode') ? 'dark' : 'day';
            localStorage.setItem('wh-theme', theme);
        });

        const $grid = $container.find('#wh-answer-grid');
        let word = $grid.attr('data-word') || '';
        word = word.toString().trim().toUpperCase();
        
        const $boxes = $container.find('.wh-box');
        const $revealBtn = $container.find('#reveal-answer-btn');
        const $postRevealActions = $container.find('.wh-post-reveal-actions');
        const $revealAgainBtn = $container.find('#reveal-again-btn');

        function revealBox(index) {
            if (!word || index < 0 || index >= word.length) return;
            const $box = $container.find(`.wh-box[data-index="${index}"]`);
            if ($box.length && !$box.hasClass('revealed')) {
                $box.addClass('revealing');
                setTimeout(() => { $box.addClass('revealed'); }, 50);
                setTimeout(() => { $box.removeClass('revealing'); }, 600);
            }
        }

        $container.off('click', '.wh-box').on('click', '.wh-box', function(e) {
            e.preventDefault();
            revealBox(jQuery(this).data('index'));
            checkAllRevealed();
        });

        function checkAllRevealed() {
            if ($container.find('.wh-box.revealed').length === word.length) {
                $revealBtn.addClass('wh-hidden').prop('disabled', true);
                $postRevealActions.addClass('wh-visible');
            }
        }

        $revealBtn.off('click').on('click', function() {
            if (!word || jQuery(this).hasClass('wh-hidden')) return;
            jQuery(this).prop('disabled', true);
            for (let i = 0; i < word.length; i++) {
                setTimeout(() => {
                    revealBox(i);
                    if (i === word.length - 1) {
                        setTimeout(() => {
                            $revealBtn.addClass('wh-hidden');
                            $postRevealActions.addClass('wh-visible');
                        }, 800);
                    }
                }, i * 180);
            }
        });

        $revealAgainBtn.off('click').on('click', function() {
            $boxes.removeClass('revealed');
            $postRevealActions.removeClass('wh-visible');
            $revealBtn.removeClass('wh-hidden').prop('disabled', false);
        });

        $container.off('click', '.wh-hint-card.locked').on('click', '.wh-hint-card.locked', function(e) {
            const $card = jQuery(this);
            $card.fadeOut(200, function() {
                $card.removeClass('locked').addClass('unlocked').fadeIn(400);
            });
        });
    }
});
