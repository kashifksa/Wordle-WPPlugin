document.addEventListener('DOMContentLoaded', () => {

    // --- Restore Dark Mode Theme from localStorage ---
    const savedTheme = localStorage.getItem('wh-theme');
    if (savedTheme === 'dark') {
        jQuery('body').addClass('dark-mode');
    }

    // 1. CENTRALIZE DATE LOGIC (User Browser Time)
    const params = new URLSearchParams(window.location.search);
    const testDate = params.get('date');
    
    const d = new Date();
    const today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const finalDate = testDate || today;
    
    console.log("Wordle Hint Pro - Target Date:", finalDate);

    // --- 1.5 ARCHIVE TIMEZONE FILTER (Hide future dates) ---
    jQuery('.wh-archive-card').each(function() {
        const cardDate = jQuery(this).attr('data-date');
        if (cardDate && cardDate > today) {
            jQuery(this).remove();
        }
    });

    // --- 1.6 AJAX ARCHIVE NAVIGATION (Instant Switching) ---
    function navigateToDate(dateStr) {
        const $container = jQuery('.wordle-hint-container');
        if (!$container.length) return;

        $container.css('opacity', '0.5');
        fetch(wordleHintData.apiUrl + 'data?date=' + dateStr)
            .then(res => res.json())
            .then(apiRes => {
                if (apiRes.success) {
                    populateUI(apiRes.data);
                    
                    // Update URL without refresh
                    const newUrl = new URL(window.location.href);
                    newUrl.searchParams.set('date', dateStr);
                    window.history.pushState({ date: dateStr }, '', newUrl.href);
                }
                $container.css('opacity', '1');
            })
            .catch(() => $container.css('opacity', '1'));
    }

    jQuery(document).on('click', '.wh-nav-btn', function() {
        const isNext = jQuery(this).attr('id') === 'wh-next-date';
        const currentDataDate = jQuery('.wordle-hint-container .wh-date').attr('data-current-date') || finalDate;
        
        const dateObj = new Date(currentDataDate + 'T00:00:00');
        if (isNext) {
            dateObj.setDate(dateObj.getDate() + 1);
        } else {
            dateObj.setDate(dateObj.getDate() - 1);
        }

        const newDateStr = dateObj.getFullYear() + '-' + String(dateObj.getMonth() + 1).padStart(2, '0') + '-' + String(dateObj.getDate()).padStart(2, '0');
        
        // Future Guard
        if (newDateStr > today) return;

        navigateToDate(newDateStr);
    });

    jQuery(document).on('click', '.wh-archive-link', function(e) {
        const linkUrl = new URL(jQuery(this).attr('href'));
        const dateParam = linkUrl.searchParams.get('date');
        
        if (dateParam) {
            e.preventDefault();
            navigateToDate(dateParam);
            
            // Smooth scroll to board
            jQuery('html, body').animate({
                scrollTop: jQuery('.wordle-hint-container').offset().top - 100
            }, 600);
        }
    });

    // Handle Browser "Back" button for AJAX dates
    window.onpopstate = function(event) {
        const urlParams = new URLSearchParams(window.location.search);
        const popDate = urlParams.get('date') || today;
        
        fetch(wordleHintData.apiUrl + 'data?date=' + popDate)
            .then(res => res.json())
            .then(apiRes => {
                if (apiRes.success) populateUI(apiRes.data);
            });
    };

    // --- 1.7 AUTO-SCROLL TO HINTS (Initial Load) ---
    if (testDate) {
        const $board = jQuery('#wordle-hint-pro');
        if ($board.length) {
            jQuery('html, body').animate({
                scrollTop: $board.offset().top - 100
            }, 600);
        }
    }

    // --- 1.8 INITIALIZE CALENDAR (Flatpickr) ---
    function initCalendar() {
        if (typeof flatpickr === 'undefined') {
            console.warn("Wordle Hint Pro - Flatpickr not loaded yet.");
            return;
        }

        const $datePickerInput = document.getElementById('wh-date-picker');
        if (!$datePickerInput) return;

        // Initialize and store on window for global access if needed
        window.whFlatpickr = flatpickr($datePickerInput, {
            dateFormat: "Y-m-d",
            maxDate: "today",
            disableMobile: true,
            monthSelectorType: "static",
            position: "auto center",
            onReady: function(selectedDates, dateStr, instance) {
                jQuery(instance.calendarContainer).addClass('wh-premium-calendar');
            },
            onChange: function(selectedDates, dateStr) {
                if (dateStr) {
                    navigateToDate(dateStr);
                }
            }
        });
    }

    // Use delegation so it works even if elements are swapped or during early clicks
    jQuery(document).on('click', '#wh-calendar-trigger', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (window.whFlatpickr) {
            window.whFlatpickr.open();
        } else if (typeof flatpickr !== 'undefined') {
            initCalendar();
            if (window.whFlatpickr) window.whFlatpickr.open();
        } else {
            alert("Calendar is still loading. Please try again in a moment.");
        }
    });

    // Initial attempt
    initCalendar();

    // --- 1.9 INSTANT ACTIVATION ---
    initGameLogic(jQuery('.wordle-hint-container'));

    const jsonUrl = typeof wordleHintData !== 'undefined'
        ? wordleHintData.pluginUrl + 'wordle-cache.json'
        : '/wp-content/plugins/Wordle-WPPlugin/wordle-cache.json';

    // 2. VERSIONED FETCH (date-based cache busting)
    fetch(jsonUrl + '?v=' + today)
        .then(response => response.json())
        .then(json => {
            const entry = (json.data && json.data[finalDate]) ? json.data[finalDate] : null;
            
            if (entry) {
                populateUI(entry);
            } else if (testDate) {
                // FALLBACK: If date is requested but not in cache, fetch from API
                fetch(wordleHintData.apiUrl + 'data?date=' + finalDate)
                    .then(res => res.json())
                    .then(apiRes => {
                        if (apiRes.success) {
                            populateUI(apiRes.data);
                        }
                    });
            }
        })
        .catch(error => {
            console.warn("Wordle Hint Pro - Cache fetch skipped or failed.");
        });

    function populateUI(data) {
        const $container = jQuery('.wordle-hint-container');
        if (!$container.length || !data) return;

        console.log("Wordle Hint Pro - Populating UI with:", data);

        // Normalize data fields (Handle both Cache and raw DB formats)
        const puzzleDate = data.date;
        const puzzleNum = data.number || data.puzzle_number;
        const vowels = (data.vowels !== undefined) ? data.vowels : data.vowel_count;
        const startsWith = data.starts_with || data.first_letter;
        const word = data.word || '';

        // Extract hints safely
        let h1 = '', h2 = '', h3 = '', h4 = '';
        if (data.hints) {
            h1 = data.hints.vague;
            h2 = data.hints.category;
            h3 = data.hints.specific;
            h4 = data.hints.final;
        } else {
            h1 = data.hint1;
            h2 = data.hint2;
            h3 = data.hint3;
            h4 = data.final_hint;
        }

        // Update Header & Stats
        const dateObj = new Date(puzzleDate + 'T00:00:00');
        const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        
        const $dateEl = $container.find('.wh-date');
        $dateEl.text(formattedDate).attr('data-current-date', puzzleDate);
        
        // Toggle Next Button Visibility (Don't show if it's today)
        if (puzzleDate >= today) {
            $container.find('#wh-next-date').css('visibility', 'hidden');
        } else {
            $container.find('#wh-next-date').css('visibility', 'visible');
        }

        $container.find('.wh-puzzle-num').text('#' + puzzleNum);
        $container.find('.wh-stat-item:contains("Vowels") .wh-highlight').text(vowels);
        $container.find('.wh-stat-item:contains("Starts With") .wh-highlight').text(startsWith);

        // Update Hint Cards
        $container.find('.wh-hint-card[data-hint="1"] .wh-hint-text').text(h1);
        $container.find('.wh-hint-card[data-hint="2"] .wh-hint-text').text(h2);
        $container.find('.wh-hint-card[data-hint="3"] .wh-hint-text').text(h3);
        $container.find('.wh-hint-card[data-hint="4"] .wh-hint-text').text(h4);

        // Reset cards to locked state for new date
        $container.find('.wh-hint-card').removeClass('unlocked').addClass('locked').show();

        // Update Answer Grid
        const $grid = $container.find('#wh-answer-grid');
        $grid.attr('data-word', word.toUpperCase());
        $grid.find('.wh-box').removeClass('revealed'); // Hide answer for new date

        const letters = word.toUpperCase().split('');
        const tiles = $container.find('.wh-box-back');
        letters.forEach((letter, index) => {
            if (tiles[index]) {
                jQuery(tiles[index]).text(letter);
            }
        });

        // Initialize Game Logic
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
        
        // Sync toggle knob visual state with current body class
        // (dark-mode may already be set from the top-of-page restoration)
        // CSS handles the knob position via body.dark-mode selector — no JS needed here.

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
