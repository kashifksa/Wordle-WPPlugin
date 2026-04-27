jQuery(document).ready(function($) {
    // --- Timezone & Date Logic (CRITICAL) ---
    const $container = $('.wordle-hint-container');
    if ($container.length) {
        const getLocalDate = () => {
            const d = new Date();
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };

        const localDate = getLocalDate();
        const urlParams = new URLSearchParams(window.location.search);
        const urlDate = urlParams.get('wh_date');

        // Automatically sync to local date if no specific date is requested
        if (!urlDate) {
            urlParams.set('wh_date', localDate);
            window.location.search = urlParams.toString();
            return; 
        }
    }

    // --- Theme Toggle Logic ---
    const $body = $('body');
    const $themeToggle = $('#wh-theme-toggle');
    const currentTheme = localStorage.getItem('wh-theme') || 'day';

    if (currentTheme === 'dark') {
        $body.addClass('dark-mode');
    }

    $themeToggle.on('click', function() {
        $body.toggleClass('dark-mode');
        const theme = $body.hasClass('dark-mode') ? 'dark' : 'day';
        localStorage.setItem('wh-theme', theme);
    });

    // --- Wordle Hint Logic ---
    $('.wordle-hint-container').each(function() {
        const $container = $(this);
        const $grid = $container.find('#wh-answer-grid');
        
        let word = $grid.attr('data-word') || '';
        word = word.toString().trim().toUpperCase();
        
        const $boxes = $container.find('.wh-box');
        const $revealBtn = $container.find('#reveal-answer-btn');
        const $postRevealActions = $container.find('.wh-post-reveal-actions');
        const $revealAgainBtn = $container.find('#reveal-again-btn');

        // Function to reveal a specific box
        function revealBox(index, isSequential = false) {
            if (!word || index < 0 || index >= word.length) return;

            const letter = word[index];
            const $box = $container.find(`.wh-box[data-index="${index}"]`);
            
            if ($box.length && !$box.hasClass('revealed')) {
                $box.find('.wh-box-back').text(letter);
                $box.addClass('revealing');
                
                setTimeout(() => {
                    $box.addClass('revealed');
                }, 50);

                setTimeout(() => {
                    $box.removeClass('revealing');
                }, 600);
            }
        }

        // Individual Box Click Reveal
        $container.on('click', '.wh-box', function(e) {
            e.preventDefault();
            const index = $(this).data('index');
            revealBox(index);
            checkAllRevealed();
        });

        function checkAllRevealed() {
            if ($container.find('.wh-box.revealed').length === word.length) {
                // Stabilized reveal: Use CSS classes instead of hiding elements
                $revealBtn.addClass('wh-hidden').prop('disabled', true);
                $postRevealActions.addClass('wh-visible');
            }
        }

        // Reveal All Answer Animation
        $revealBtn.on('click', function() {
            if (!word || $(this).hasClass('wh-hidden')) return;
            
            $(this).prop('disabled', true);
            
            for (let i = 0; i < word.length; i++) {
                setTimeout(() => {
                    revealBox(i, true);
                    
                    if (i === word.length - 1) {
                        setTimeout(() => {
                            $revealBtn.addClass('wh-hidden');
                            $postRevealActions.addClass('wh-visible');
                        }, 800);
                    }
                }, i * 180);
            }
        });

        // Reveal Again logic
        $revealAgainBtn.on('click', function() {
            $boxes.removeClass('revealed').find('.wh-box-back').text('');
            $postRevealActions.removeClass('wh-visible');
            $revealBtn.removeClass('wh-hidden').prop('disabled', false);
        });

        // Progressive Hint Unlocking (Click button OR entire card)
        $container.on('click', '.wh-hint-card.locked', function(e) {
            const $card = $(this);
            
            $card.fadeOut(200, function() {
                $card.removeClass('locked').addClass('unlocked').fadeIn(400);
            });
        });
    });

    console.log('Wordle Hint Pro: Premium Reveal UI Initialized.');
});
