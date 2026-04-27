jQuery(document).ready(function($) {
    const $grid = $('#wh-answer-grid');
    const word = $grid.data('word') ? $grid.data('word').toString().toUpperCase() : '';
    const $boxes = $('.wh-box');
    const $revealBtn = $('#reveal-answer-btn');
    const $revealAgainBtn = $('#reveal-again-btn');

    // Reveal Answer Animation
    $revealBtn.on('click', function() {
        if (!word) return;
        
        $(this).prop('disabled', true).find('.btn-text').text('Answer Revealed');
        
        word.split('').forEach((letter, index) => {
            setTimeout(() => {
                const $box = $boxes.eq(index);
                if ($box.length) {
                    $box.find('.wh-box-back').text(letter);
                    $box.addClass('revealed');
                    
                    // Add success sound effect feel with a glow
                    setTimeout(() => {
                        $box.find('.wh-box-back').css('box-shadow', '0 0 20px var(--wordle-green)');
                    }, 300);
                }
            }, index * 200); // 200ms delay between letters
        });

        setTimeout(() => {
            $revealAgainBtn.fadeIn();
        }, (word.length * 200) + 500);
    });

    // Reveal Again logic
    $revealAgainBtn.on('click', function() {
        $boxes.removeClass('revealed').find('.wh-box-back').css('box-shadow', '');
        $revealBtn.prop('disabled', false).find('.btn-text').text('Show Today\'s Answer');
        $(this).hide();
    });

    // Progressive Hint Unlocking
    $('.wh-unlock-btn').on('click', function() {
        const $card = $(this).closest('.wh-hint-card');
        
        $card.fadeOut(200, function() {
            $card.removeClass('locked').addClass('unlocked').fadeIn(400);
        });
    });

    console.log('Wordle Hint Pro: Premium UI Initialized.');
});
