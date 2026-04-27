jQuery(document).ready(function($) {
    // Process each Wordle container independently
    $('.wordle-hint-container').each(function() {
        const $container = $(this);
        const $grid = $container.find('#wh-answer-grid');
        
        // Fetch the word and ensure it's a string
        let word = $grid.attr('data-word') || '';
        word = word.toString().trim().toUpperCase();
        
        const $boxes = $container.find('.wh-box');
        const $revealBtn = $container.find('#reveal-answer-btn');
        const $revealAgainBtn = $container.find('#reveal-again-btn');

        console.log('Wordle Hint Pro: Container initialized for word:', word ? 'OK' : 'MISSING');

        // Function to reveal a specific box
        function revealBox(index) {
            if (!word || index < 0 || index >= word.length) {
                console.warn('Wordle Hint Pro: Cannot reveal index', index, 'for word', word);
                return;
            }

            const letter = word[index];
            const $box = $container.find(`.wh-box[data-index="${index}"]`);
            
            if ($box.length && !$box.hasClass('revealed')) {
                // Set the letter BEFORE adding the class to ensure it's there when it flips
                $box.find('.wh-box-back').text(letter);
                $box.addClass('revealed');
                
                setTimeout(() => {
                    $box.find('.wh-box-back').css('box-shadow', '0 0 20px var(--wordle-green)');
                }, 300);
            }
        }

        // Individual Box Click Reveal
        $container.on('click', '.wh-box', function(e) {
            e.preventDefault();
            const index = $(this).data('index');
            console.log('Wordle Hint Pro: Box clicked', index);
            revealBox(index);
        });

        // Reveal All Answer Animation
        $revealBtn.on('click', function() {
            if (!word) return;
            
            $(this).prop('disabled', true).find('.btn-text').text('Answer Revealed');
            
            for (let i = 0; i < word.length; i++) {
                setTimeout(() => {
                    revealBox(i);
                }, i * 200);
            }

            setTimeout(() => {
                $revealAgainBtn.fadeIn();
            }, (word.length * 200) + 500);
        });

        // Reveal Again logic
        $revealAgainBtn.on('click', function() {
            $boxes.removeClass('revealed').find('.wh-box-back').css('box-shadow', '').text('');
            $revealBtn.prop('disabled', false).find('.btn-text').text('Show Today\'s Answer');
            $(this).hide();
        });

        // Progressive Hint Unlocking
        $container.find('.wh-unlock-btn').on('click', function() {
            const $card = $(this).closest('.wh-hint-card');
            
            $card.fadeOut(200, function() {
                $card.removeClass('locked').addClass('unlocked').fadeIn(400);
            });
        });
    });

    console.log('Wordle Hint Pro: Premium UI Fully Loaded.');
});
