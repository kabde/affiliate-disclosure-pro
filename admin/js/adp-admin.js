jQuery(function($) {
    // Preset buttons
    $('#adp-preset-fr').on('click', function() {
        $('textarea[name="adp_settings[text]"]').val("Cet article contient des liens d'affiliation. Si vous effectuez un achat via ces liens, nous pouvons percevoir une commission, sans frais supplémentaires pour vous.");
    });
    $('#adp-preset-en').on('click', function() {
        $('textarea[name="adp_settings[text]"]').val("This post contains affiliate links. If you make a purchase through these links, we may earn a commission at no extra cost to you.");
    });

    // All categories toggle
    $('#adp-all-categories').on('change', function() {
        $('input[name="adp_settings[categories][]"]').prop('checked', this.checked);
    });

    // Metabox: show/hide custom text
    $('input[name="adp_override"]').on('change', function() {
        var val = $(this).val();
        $('#adp-custom-text-wrap').toggle(val === 'enable');
        $('#adp-position-wrap').toggle(val !== 'disable');
    });
    // Init
    var current = $('input[name="adp_override"]:checked').val();
    if (current) {
        $('#adp-custom-text-wrap').toggle(current === 'enable');
        $('#adp-position-wrap').toggle(current !== 'disable');
    }

    // Live preview in Apparence tab
    function updatePreview() {
        var style = $('select[name="adp_settings[style]"]').val() || 'box';
        var bg = $('input[name="adp_settings[color_bg]"]').val() || '#f8f9fa';
        var color = $('input[name="adp_settings[color_text]"]').val() || '#6b7280';
        var border = $('input[name="adp_settings[color_border]"]').val() || '#e5e7eb';
        var radius = $('input[name="adp_settings[border_radius]"]').val() || '6';
        var icon = $('input[name="adp_settings[show_icon]"]').is(':checked');
        var text = $('textarea[name="adp_settings[text]"]').val() || 'Sample disclosure text...';

        var $preview = $('#adp-preview');
        $preview.attr('class', 'adp-disclosure adp-style-' + style);
        $preview.css({
            '--adp-bg': bg,
            '--adp-color': color,
            '--adp-border': border,
            '--adp-radius': radius + 'px'
        });
        $preview.html((icon ? '<span class="adp-icon">&#8505;&#65039; </span>' : '') + '<p class="adp-text" style="display:inline">' + $('<span>').text(text.substring(0, 100) + '...').html() + '</p>');
    }

    $('select[name="adp_settings[style]"], input[name="adp_settings[color_bg]"], input[name="adp_settings[color_text]"], input[name="adp_settings[color_border]"], input[name="adp_settings[border_radius]"], input[name="adp_settings[show_icon]"]').on('change input', updatePreview);
    if ($('#adp-preview').length) updatePreview();
});
