$(document).ready(function () {

    let tooltip = null;
    let popper = null;

    $('body')

    .on('mouseenter', '.button-tooltip-target', function () {

        // Remove the old tooltip if it is still there
        popper?.destroy();
        tooltip?.remove();

        // Create the tooltip
        tooltip = document.createElement('div');
        tooltip.className = 'button-tooltip';

        const label = document.createElement('span');
        label.textContent = $(this).data('tooltip') || '';
        const arrow = document.createElement('div');
        arrow.className = 'button-tooltip-arrow';
        tooltip.append(label, arrow);

        tooltip.style.backgroundColor = getComputedStyle(this).getPropertyValue('--tooltip-bg').trim();

        document.body.appendChild(tooltip);

        // Show the tooltip first
        tooltip.setAttribute('data-show', '');

        // Initialize Popper
        popper = Popper.createPopper(this, tooltip, {
            placement: 'auto',
            modifiers: [
                {
                    name: 'flip',
                    options: {
                        allowedAutoPlacements: ['top', 'bottom'],
                        fallbackPlacements: ['top', 'bottom']
                    }
                },
                {
                    name: 'offset',
                    options: {
                        offset: [0, 10]
                    }
                },
                {
                    name: 'preventOverflow',
                    options: {
                        padding: 16
                    }
                }
            ]
        });

        popper.update();

    })

    .on('mouseleave', '.button-tooltip-target', function () {

        if (popper) {
            popper.destroy();
            popper = null;
        }

        if (tooltip) {
            tooltip.remove();
            tooltip = null;
        }

    });

});