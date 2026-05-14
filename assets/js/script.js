document.addEventListener('DOMContentLoaded', () => {

    // --- Restore Dark Mode Theme from localStorage ---
    const savedTheme = localStorage.getItem('wh-theme');
    if (savedTheme === 'dark') {
        jQuery('body').addClass('dark-mode');
    }

    // 1. CENTRALIZE DATE LOGIC (Support date and wh_date)
    const params = new URLSearchParams(window.location.search);
    const testDate = params.get('date') || params.get('wh_date');
    
    const d = new Date();
    const today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const finalDate = testDate || today;
    
    console.log("Wordle Hint Pro - Target Date:", finalDate);

    // --- 1.5 ARCHIVE TIMEZONE FILTER (Hide future dates) ---
    jQuery('.wh-archive-card, .wh-roundup-row').each(function() {
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
        // Zap Discovery area instantly so old content doesn't flicker during fetch
        jQuery('#wh-discovery-section').addClass('wh-no-transition').removeClass('wh-visible');
        setTimeout(() => jQuery('#wh-discovery-section').removeClass('wh-no-transition'), 100);
        fetch(wordleHintData.apiUrl + 'data?date=' + dateStr)
            .then(res => res.json())
            .then(apiRes => {
                if (apiRes.success) {
                    populateUI(apiRes);
                    
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

    jQuery(document).on('click', '.wh-archive-link, .wh-compact-card', function(e) {
        const href = jQuery(this).attr('href');
        if (!href) return;
        
        const linkUrl = new URL(href, window.location.origin);
        const dateParam = linkUrl.searchParams.get('date') || linkUrl.searchParams.get('wh_date');
        
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
                if (apiRes.success) populateUI(apiRes);
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
        if (typeof flatpickr === 'undefined') return;

        const $datePickerInput = document.getElementById('wh-date-picker');
        if (!$datePickerInput) return;

        // Initialize and store on window for global access
        window.whFlatpickr = flatpickr($datePickerInput, {
            dateFormat: "Y-m-d",
            defaultDate: finalDate, // Start with the date from URL
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

    // --- 1.8.5 SMART TOOLBAR TIMER ---
    function initToolbarTimer() {
        const $timers = jQuery('.wh-timer-val');
        if (!$timers.length) return;

        function updateTimer() {
            const now = new Date();
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(0, 0, 0, 0);

            const diff = tomorrow - now;
            if (diff <= 0) {
                $timers.text("00:00:00");
                return;
            }

            const h = Math.floor(diff / 3600000);
            const m = Math.floor((diff % 3600000) / 60000);
            const s = Math.floor((diff % 60000) / 1000);

            const timeStr = String(h).padStart(2, '0') + ":" + 
                            String(m).padStart(2, '0') + ":" + 
                            String(s).padStart(2, '0');
            
            $timers.text(timeStr);
        }

        updateTimer();
        setInterval(updateTimer, 1000);
    }
    initToolbarTimer();

    // --- 1.9 INSTANT ACTIVATION ---
    initGameLogic(jQuery('.wordle-hint-container'));

    const jsonUrl = typeof wordleHintData !== 'undefined'
        ? wordleHintData.pluginUrl + 'wordle-data.json'
        : '/wp-content/plugins/Wordle-WPPlugin/wordle-data.json';

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
        
        // Defensive Word Extraction: Don't overwrite if word is missing in new data
        const newWord = data.word || data.answer || data.puzzle_word || '';
        const currentWord = $container.find('#wh-answer-grid').attr('data-word') || '';
        const word = (newWord && newWord.length === 5) ? newWord : currentWord;

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
        
        // Sync calendar instance if it exists
        if (window.whFlatpickr) {
            window.whFlatpickr.setDate(puzzleDate, false);
        }
        
        // Toggle Next Button Visibility (Don't show if it's today)
        if (puzzleDate >= today) {
            $container.find('#wh-next-date').css('visibility', 'hidden');
        } else {
            $container.find('#wh-next-date').css('visibility', 'visible');
        }

        $container.find('.wh-puzzle-num').text('#' + puzzleNum);
        $container.find('.wh-stat-item:contains("Vowels") .wh-highlight').text(vowels);

        // Update Difficulty Badge
        const difficulty = data.difficulty || (data.stats ? data.stats.difficulty : 0);
        const avgGuesses = data.average_guesses || (data.stats ? data.stats.average_guesses : 0);
        const $diffBadge = $container.find('.wh-difficulty-badge');
        
        if (difficulty > 0) {
            $diffBadge.show().attr('data-value', difficulty);
            
            let diffLabel = 'Moderate';
            if (difficulty <= 3.7) diffLabel = 'Very Easy';
            else if (difficulty <= 4.0) diffLabel = 'Moderate';
            else if (difficulty <= 4.3) diffLabel = 'Hard';
            else diffLabel = 'Insane';
            
            $diffBadge.find('.wh-difficulty-label').text(diffLabel);
            
            // Update Distribution Chart
            const $statsSection = jQuery('#wh-stats-summary');
            const $chart = $statsSection.find('.wh-dist-chart');
            let dist = [];
            
            if (typeof data.guess_distribution === 'string' && data.guess_distribution !== '[]') {
                try { dist = JSON.parse(data.guess_distribution); } catch(e) {}
            } else if (data.stats && data.stats.distribution) {
                dist = data.stats.distribution;
            } else if (Array.isArray(data.guess_distribution)) {
                dist = data.guess_distribution;
            }
            
            if (dist && dist.length) {
                $statsSection.show();
                $statsSection.find('.wh-stats-avg strong').text(avgGuesses);
                
                let $bars = $chart.find('.wh-dist-bar');
                
                // If bars don't exist (e.g. PHP didn't render them), rebuild them
                if ($bars.length === 0) {
                    let chartHtml = '';
                    dist.forEach((pct, i) => {
                        const label = i + 1;
                        chartHtml += `
                            <div class="wh-dist-bar-wrapper" title="${pct}% solved in ${label}">
                                <div class="wh-dist-label">${label}</div>
                                <div class="wh-dist-bar-container">
                                    <div class="wh-dist-bar" style="width:${pct}%"></div>
                                </div>
                                <div class="wh-dist-pct">${pct}%</div>
                            </div>`;
                    });
                    $chart.html(chartHtml);
                    $bars = $chart.find('.wh-dist-bar');
                } else {
                    const $pcts = $chart.find('.wh-dist-pct');
                    dist.forEach((pct, i) => {
                        if ($bars[i]) jQuery($bars[i]).css('width', pct + '%');
                        if ($pcts[i]) jQuery($pcts[i]).text(pct + '%');
                        // Update wrapper title too
                        jQuery($bars[i]).closest('.wh-dist-bar-wrapper').attr('title', pct + '% solved in ' + (i+1));
                    });
                }
            } else {
                $statsSection.hide();
            }
        } else {
            $diffBadge.hide();
            jQuery('#wh-stats-summary').hide();
        }

        // Update Hint Cards
        $container.find('.wh-hint-card[data-hint="1"] .wh-hint-text').text(h1);
        $container.find('.wh-hint-card[data-hint="2"] .wh-hint-text').text(h2);
        $container.find('.wh-hint-card[data-hint="3"] .wh-hint-text').text(h3);
        $container.find('.wh-hint-card[data-hint="4"] .wh-hint-text').text(h4);

        // Reset cards to locked state for new date
        $container.find('.wh-hint-card').removeClass('unlocked').addClass('locked').show();

        const $grid = $container.find('#wh-answer-grid');
        const $revealBtn = $container.find('#reveal-answer-btn');
        const $toolbar = $container.find('#wh-post-reveal-toolbar');
        const $prevBtnLabel = $toolbar.find('#wh-toolbar-prev .label');
        
        $grid.attr('data-word', word.toUpperCase());
        $grid.find('.wh-box').removeClass('revealed'); // Hide answer for new date
        
        // Reset Toolbar & Reveal Button Visibility
        $revealBtn.removeClass('wh-hidden').prop('disabled', false);
        $toolbar.removeClass('wh-visible').addClass('wh-hidden');

        // Dynamic Button Text (Past dates shouldn't say "Today's")
        if (puzzleDate < today) {
            $revealBtn.find('.btn-text').text('Show Answer');
            $prevBtnLabel.text('Previous Day');
        } else {
            $revealBtn.find('.btn-text').text("Show Today's Answer");
            $prevBtnLabel.text('Previous Day');
        }

        // Update Download Card dates for AJAX switches
        $container.find('#wh-download-card, #wh-download-story').attr('data-date', puzzleDate);

        // Inject Letters into the back of each tile
        if (word && word.length === 5) {
            const letters = word.toUpperCase().split('');
            $container.find('.wh-box').each(function(i) {
                const letter = letters[i] || '';
                jQuery(this).find('.wh-box-back').text(letter);
            });
        }

        // Update Discovery Section (Handle both flat raw DB and nested Cache formats)
        const dict = data.dictionary || data;
        $container.find('#wh-pos').text(dict.part_of_speech || '');
        $container.find('#wh-pronunciation').text(dict.pronunciation || '');
        $container.find('#wh-definition').text(dict.definition || '');
        $container.find('#wh-etymology').text(dict.etymology || '');
        $container.find('#wh-first-use').text(dict.first_known_use || '');
        
        const $firstUseWrapper = $container.find('#wh-first-use-wrapper');
        if (dict.first_known_use) $firstUseWrapper.show(); else $firstUseWrapper.hide();

        const $exampleWrapper = $container.find('#wh-example-wrapper');
        const example = dict.example_sentence || dict.example;
        if (example) {
            $exampleWrapper.show().find('#wh-example').text(example);
        } else {
            $exampleWrapper.hide();
        }

        const audioUrl = dict.audio_url || dict.audio;
        const $audioBtn = $container.find('#wh-audio-btn');
        if (audioUrl) {
            $audioBtn.show().attr('data-src', audioUrl);
        } else {
            $audioBtn.hide();
        }

        // Handle Tags (Synonyms/Antonyms)
        function updateTags($el, rawData) {
            let tags = [];
            if (typeof rawData === 'string' && rawData !== '[]') {
                try { tags = JSON.parse(rawData); } catch(e) {}
            } else if (Array.isArray(rawData)) {
                tags = rawData;
            }
            
            $el.empty();
            if (tags && tags.length) {
                tags.slice(0, 8).forEach(tag => {
                    $el.append(`<span class="wh-tag ${$el.attr('id') === 'wh-antonyms' ? 'wh-tag-alt' : ''}">${tag}</span>`);
                });
            }
        }
        updateTags($container.find('#wh-synonyms'), dict.synonyms);
        updateTags($container.find('#wh-antonyms'), dict.antonyms);

        // Reset Discovery Visibility (Instant for date switch)
        $container.find('#wh-discovery-section').addClass('wh-no-transition').removeClass('wh-visible');
        setTimeout(() => $container.find('#wh-discovery-section').removeClass('wh-no-transition'), 100);

        // Initialize Game Logic
        initGameLogic($container);

        // Render Lucide Icons for new content
        if (window.lucide) lucide.createIcons();
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
        const $toolbar = $container.find('#wh-post-reveal-toolbar');

        // New Toolbar Button Listeners
        $container.off('click', '#wh-toolbar-prev').on('click', '#wh-toolbar-prev', function() {
            const currentDataDate = jQuery('.wordle-hint-container .wh-date').attr('data-current-date') || finalDate;
            const dateObj = new Date(currentDataDate + 'T00:00:00');
            dateObj.setDate(dateObj.getDate() - 1);
            const newDateStr = dateObj.getFullYear() + '-' + String(dateObj.getMonth() + 1).padStart(2, '0') + '-' + String(dateObj.getDate()).padStart(2, '0');
            navigateToDate(newDateStr);
        });

        $container.off('click', '#wh-toolbar-calendar').on('click', '#wh-toolbar-calendar', function() {
            if (window.whFlatpickr) window.whFlatpickr.open();
        });


        // Copy Results Logic (Emojis)
        $container.off('click', '#wh-copy-results').on('click', '#wh-copy-results', function() {
            const puzzleNum = $container.find('.wh-puzzle-num').text();
            const dateStr = $container.find('.wh-date').text();
            const word = $grid.attr('data-word') || '';
            const $btn = jQuery(this);
            const originalHtml = $btn.html();

            // NYT Style Grid: 🟩🟩🟩🟩🟩
            // We represent hints used as yellow rows (to avoid giving away the letters but showing effort)
            const unlockedHints = $container.find('.wh-hint-card:not(.locked)').length;
            
            let gridString = "";
            if (unlockedHints > 0) {
                for (let i = 0; i < Math.min(unlockedHints, 4); i++) {
                    gridString += "🟨🟨🟨🟨🟨\n";
                }
            }
            gridString += "🟩🟩🟩🟩🟩";

            const siteUrl = window.location.href.split('?')[0];
            const shareText = `Wordle ${puzzleNum} (${unlockedHints}/4 Hints)\n${dateStr}\n\n${gridString}\n\nSolved at:\n🔗 ${siteUrl}`;

            const copySuccess = () => {
                $btn.html('<span class="icon">✅</span> <span class="label">Copied!</span>').addClass('copied');
                setTimeout(() => {
                    $btn.html(originalHtml).removeClass('copied');
                }, 2000);
            };

            if (navigator.clipboard) {
                navigator.clipboard.writeText(shareText).then(copySuccess).catch(err => {
                    console.error('Failed to copy: ', err);
                });
            } else {
                const textArea = document.createElement("textarea");
                textArea.value = shareText;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand("copy");
                document.body.removeChild(textArea);
                copySuccess();
            }
        });

        function revealBox(index) {
            const $grid = $container.find('#wh-answer-grid');
            const currentWord = $grid.attr('data-word') || '';
            if (!currentWord || index < 0 || index >= currentWord.length) return;
            
            const $box = $container.find(`.wh-box[data-index="${index}"]`);
            if ($box.length && !$box.hasClass('revealed')) {
                // Hard Injection: Set the text right before revealing
                const letter = currentWord[index].toUpperCase();
                $box.find('.wh-box-back').text(letter);
                
                $box.addClass('revealing');
                setTimeout(() => { 
                    $box.addClass('revealed');
                    checkAllRevealed(); 
                }, 50);
                setTimeout(() => { $box.removeClass('revealing'); }, 600);
            }
        }

        $container.off('click', '.wh-box').on('click', '.wh-box', function(e) {
            e.preventDefault();
            revealBox(jQuery(this).data('index'));
        });

        function checkAllRevealed() {
            if ($container.find('.wh-box.revealed').length === word.length) {
                $revealBtn.addClass('wh-hidden').prop('disabled', true);
                $toolbar.removeClass('wh-hidden').addClass('wh-visible');
                
                // Show Discovery Section with a small delay for premium feel
                setTimeout(() => {
                    $container.find('#wh-discovery-section').addClass('wh-visible');
                }, 400);
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
                            $toolbar.removeClass('wh-hidden').addClass('wh-visible');
                            
                            // Show Discovery Section
                            jQuery('#wh-discovery-section').addClass('wh-visible');
                        }, 800);
                    }
                }, i * 180);
            }
        });

        $container.off('click', '.wh-hint-card.locked').on('click', '.wh-hint-card.locked', function(e) {
            const $card = jQuery(this);
            $card.fadeOut(200, function() {
                $card.removeClass('locked').addClass('unlocked').fadeIn(400);
            });
        });

        $container.off('click', '#wh-download-card, #wh-download-story').on('click', '#wh-download-card, #wh-download-story', function(e) {
            e.preventDefault();
            const $btn = jQuery(this);
            const date = $btn.attr('data-date');
            const isStory = $btn.attr('id') === 'wh-download-story';
            
            if (!date) {
                console.error("Wordle Hint Pro - No date found on button");
                return;
            }
            
            const originalHtml = $btn.html();
            $btn.html('<span class="icon">⏳</span> <span class="label">Preparing...</span>').prop('disabled', true);
            
            let baseUrl = wordleHintData.apiUrl;
            if (baseUrl.endsWith('/')) {
                baseUrl = baseUrl.slice(0, -1);
            }
            const imageUrl = baseUrl + '/share-image/' + date + (isStory ? '?format=mobile' : '');
            console.log("Wordle Hint Pro - Download URL:", imageUrl);
            
            // Trigger download via hidden link
            const link = document.createElement('a');
            link.href = imageUrl;
            link.download = `wordle-hints-${date}${isStory ? '-story' : ''}.png`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            setTimeout(() => {
                $btn.html(originalHtml).prop('disabled', false);
            }, 1500);
        });

        // Audio Playback
        $container.off('click', '#wh-audio-btn').on('click', '#wh-audio-btn', function() {
            const audioSrc = jQuery(this).attr('data-src');
            const $audio = jQuery('#wh-pronunciation-audio');
            if (audioSrc && $audio.length) {
                $audio.attr('src', audioSrc);
                $audio[0].play();
                
                // Visual feedback
                jQuery(this).addClass('playing');
                setTimeout(() => jQuery(this).removeClass('playing'), 500);
            }
        });
    }

    // --- 7. COPY CLUES (SOCIAL SHARING) ---
    jQuery(document).on('click', '#wh-copy-clues', function() {
        const $btn = jQuery(this);
        const $container = jQuery('.wordle-hint-container');
        const puzzleNum = $container.find('.wh-puzzle-num').text();
        const dateStr = $container.find('.wh-date').text();
        // Remove query params for clean sharing
        const siteUrl = window.location.href.split('?')[0];

        // Gather Hints
        let text = `🧩 Wordle Hints ${puzzleNum} (${dateStr})\n\n`;
        
        $container.find('.wh-hint-card').each(function(i) {
            const label = jQuery(this).find('.wh-hint-label').text().split(':')[0];
            const hint = jQuery(this).find('.wh-hint-text').text();
            text += `${label}: ${hint}\n`;
        });

        text += `\nUnlock all hints at:\n🔗 ${siteUrl}`;

        // Copy to Clipboard
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                const originalText = $btn.html();
                $btn.addClass('success').html('<span>✅</span> Copied!');
                setTimeout(() => {
                    $btn.removeClass('success').html(originalText);
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
            });
        } else {
            // Fallback for non-HTTPS or older browsers
            const textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand("copy");
            document.body.removeChild(textArea);
            const originalText = $btn.html();
            $btn.addClass('success').html('<span>✅</span> Copied!');
            setTimeout(() => {
                $btn.removeClass('success').html(originalText);
            }, 2000);
        }
    });

    // --- 8. SUBSCRIPTION FORM LOGIC (GLOBAL) ---
    jQuery(document).on('submit', '#wh-subscribe-form', function(e) {
        e.preventDefault();
        const $form = jQuery(this);
        const $btn = $form.find('.wh-subscribe-btn');
        const $msg = $form.parent().find('#wh-subscribe-message');
        const email = $form.find('input[name="email"]').val();

        $btn.prop('disabled', true);
        $btn.find('.wh-btn-text').hide();
        $btn.find('.wh-btn-loading').show();
        $msg.removeClass('success error').hide();

        fetch(wordleHintData.apiUrl + 'subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wordleHintData.nonce
            },
            body: JSON.stringify({ email: email })
        })
        .then(res => res.json())
        .then(data => {
            $btn.prop('disabled', false);
            $btn.find('.wh-btn-text').show();
            $btn.find('.wh-btn-loading').hide();
            
            $msg.text(data.message).addClass(data.success ? 'success' : 'error').fadeIn();
            if (data.success) {
                $form.find('input').val('');
            }
        })
        .catch(() => {
            $btn.prop('disabled', false);
            $btn.find('.wh-btn-text').show();
            $btn.find('.wh-btn-loading').hide();
            $msg.text('An error occurred. Please try again later.').addClass('error').fadeIn();
        });
    });

    // --- 9. ACCESSIBILITY: KEYBOARD SUPPORT ---
    jQuery(document).on('keydown', '.wh-theme-toggle, .wh-date, .wh-box', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            jQuery(this).trigger('click');
        }
    });
    
    if (window.lucide) lucide.createIcons();
});
