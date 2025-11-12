<?php
/**
 * Color Matrix Accessibility Checker
 *
 * @package           PluginPackage
 * @author            Jon Ericson via SnippetClub.com
 * @copyright         2019 Your Name or Company Name
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       JE Color Contrast Ratios 
 * Plugin URI:        https://jonericson.com
 * Description:       Checks accessibility contrast ratios for colors using GeneratePress theme.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Jon Ericson via SnippetClub.com
 * Author URI:        https://jonericson.com
 * Text Domain:       plugin-slug
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */
/** Load CSS **/
function load_scripts() {
    wp_enqueue_style('je-color-matrix-css', plugin_dir_url(__FILE__) . 'je-color-matrix.css');
}
add_action('wp_enqueue_scripts', 'load_scripts');
 
/**
 * Converts a hex colour string into an RGB array.
 *
 * @param string $hex The hex colour string.
 * @return array An array with RGB values.
 */
function hexToRGB($hex) {
    return array(
        hexdec(substr($hex, 1, 2)),
        hexdec(substr($hex, 3, 2)),
        hexdec(substr($hex, 5, 2))
    );
}

/**
 * Calculates the luminance of a colour.
 *
 * @param string $hex The hex colour string.
 * @return float The luminance value.
 */
function luminance($hex) {
    $rgb = hexToRGB($hex);
    $a = array();
    foreach ($rgb as $v) {
        $v /= 255;
        $a[] = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return $a[0] * 0.2126 + $a[1] * 0.7152 + $a[2] * 0.0722;
}

/**
 * Calculates the contrast ratio between two colours.
 *
 * @param string $hex1 The first hex colour string.
 * @param string $hex2 The second hex colour string.
 * @return float The contrast ratio.
 */
function contrast_ratio($hex1, $hex2) {
    $l1 = luminance($hex1);
    $l2 = luminance($hex2);
    return ($l1 > $l2) ? ($l1 + 0.05) / ($l2 + 0.05) : ($l2 + 0.05) / ($l1 + 0.05);
}
/**
 * Gets the accessibility rating based on contrast ratio.
 *
 * @param float $ratio The contrast ratio.
 * @return string The accessibility rating.
 */
function get_accessibility_rating($ratio) {
    if ($ratio >= 7) {
        return 'AAA';
    } elseif ($ratio >= 4.5) {
        return 'AA';
    } elseif ($ratio >= 3) {
        return 'G';
    } else {
        return 'null'; // No rating
    }
}
/**
 * Generates an HTML table representing the accessibility matrix.
 *
 * @param array $colours An associative array of colour names and hex values.
 * @return string The generated HTML table.
 */
function generate_colour_matrix($colours) {
    // Include a toggle checkbox
    $toggle = 'Show Ratings<input type="checkbox" id="toggleRatings" name="toggleRatings"/><label class="toggle-button" for="toggleRatings"><span>Show Ratings</span></label>';

    // Build table header
    $headers = '<tr><th>Colour</th>';
    foreach ($colours as $colourName => $hex) {
        $textColour = luminance($hex) > 0.5 ? '#000' : '#FFF';
        $headers .= '<th data-tooltip="Copy to clipboard" data-colour="var(--'. $colourName .')" style="--bg-colour:' . $hex . '; --text-colour:' . $textColour . ';">';
        $headers .= '<span class="colour-name">' . $colourName . '</span><br><span class="colour-value">' . $hex . '</span></th>';
    }
    $headers .= '</tr>';

    // Build table body
    $body = '';
    foreach ($colours as $colourName1 => $hex1) {
        $textColour1 = luminance($hex1) > 0.5 ? '#000' : '#FFF';
        $body .= '<tr><th tooltip data-tooltip="Copy to clipboard" data-colour="var(--'. $colourName1 .')" style="--bg-colour:' . $hex1 . '; --text-colour:' . $textColour1 . ';">';
        $body .= '<span class="colour-name">' . $colourName1 . '</span><br><span class="colour-value">' . $hex1 . '</span></th>';

        foreach ($colours as $hex2) {
            $ratio = contrast_ratio($hex1, $hex2);
            $rating = get_accessibility_rating($ratio);

            $rating_class = 'rating-cell' . ($rating == 'G' ? ' not-text' : '');
            $colours_content = $rating !== 'G' ? '<span class="colours"><span>'. number_format($ratio,1) .'</span> : 1</span>' : '';

            $body .= ($rating !== 'null')
                ? '<td class="'. $rating_class .'" style="--bg-colour:' . $hex1 . '; --text-colour:' . $hex2 . ';">' .
                  '<span class="rating rating-' . $rating . '">' . $rating . '</span>' . $colours_content . '</td>'
                : '<td class="rating-cell"></td>';
        }
        $body .= '</tr>';
    }

    // Combine all parts and return complete table HTML
    return '<div class="accessibility-colour-checker">' . $toggle . '<table class="colour-matrix">' . $headers . $body . '</table></div>';
}
function add_clipboard_copy(){
	ob_start();
	?>
	<script>
		function copyAnchor(el){
			// Get the value from data-colour attribute
			const colourValue = el.getAttribute("data-colour");
			console.log(colourValue);

			// Use the Clipboard API to write the value to the clipboard
			navigator.clipboard.writeText(colourValue).then(function() {
				// On success, change the tooltip
				el.dataset.tooltip = 'Copied!';

				// Reset the tooltip text after 3 seconds
				setTimeout(() => {
					el.dataset.tooltip = 'Copy colour';
				}, 3000);
			}, function(err) {
				// On error, you can log or display an error message
				console.error('Could not copy text: ', err);
			});
		}

		// Attach the click event listener to all elements with the data-colour attribute
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('[data-colour]').forEach(element => {
			element.addEventListener('click', function() {
				copyAnchor(this);
				console.log(this)
			});
		});
		});

	</script>
	<?php
	return ob_get_clean();
}
/** Generate the shortcode */
function accessibility_colour_matrix(){
	if (!function_exists('generate_get_option')) {
       		return '<p>This plugin requires the GeneratePress theme.</p>';
   	}
	$generate_colours = generate_get_option( 'global_colors' );
	$colours = wp_list_pluck( $generate_colours, 'color', 'name' );

	$output = generate_colour_matrix($colours);
	$output .= add_clipboard_copy();

	return $output;
}
add_shortcode('colour_matrix', 'accessibility_colour_matrix');