<?php
/**
 * Plugin Name: Culture Object MDS Display
 * Plugin URI: http://cultureobject.co.uk
 * Description: An extension to Culture Object to provide an archive view of objects and single object view for all themes.
 * Version: 1.0.4
 * Author: Liam Gladdy / Thirty8 Digital / The Museum Platform / Jack Barber
 * Text Domain: culture-object-display
 * Author URI: https://github.com/lgladdy
 * GitHub Plugin URI: The-Museum-Platform/CultureObject-MDS-Display-Plugin
 * GitHub Branch: main
 * License: Apache 2 License
 */

require_once 'CultureObject/Display/COD.class.php';
register_activation_hook( __FILE__, array( 'CultureObject\Display\COD', 'check_versions' ) );
register_activation_hook( __FILE__, array( 'CultureObject\Display\COD', 'regenerate_permalinks' ) );
register_deactivation_hook( __FILE__, array( 'CultureObject\Display\COD', 'regenerate_permalinks' ) );
$cod = new \CultureObject\Display\COD();

function mds_make_links_clickable( $text ) {
	return preg_replace_callback(
		'!\b(https?://[^\s<>"]+)!i',
		function ( $matches ) {
			$url     = esc_url( $matches[1] );
			$display = esc_html( $matches[1] );
			return '<a href="' . $url . '">' . $display . '</a>';
		},
		$text
	);
}

function mds_enqueue_assets() {
	// Enqueue minimal handles for our inline content
	// Using false as src creates empty handles specifically for inline content
	wp_enqueue_style( 'mds-record-display', false, array(), '1.0.0' );
	wp_enqueue_script( 'mds-record-display', false, array(), '1.0.0', true );
}

function mds_cos_fields() {
	$id = get_the_ID();
	if ( ! $id ) {
		return false;
	}

	$obj       = cos_get_field( '@document' );
	$obj_admin = cos_get_field( '@admin' );

	// Validate that we have the required data structure
	if ( ! $obj || ! is_array( $obj ) || ! isset( $obj['units'] ) || ! is_array( $obj['units'] ) ) {
		return false;
	}

	$fields = array();

	// Ensure assets are enqueued before adding inline styles/scripts
	mds_enqueue_assets();

	// Enqueue inline styles for the complete record table
	wp_add_inline_style(
		'mds-record-display',
		'
		#ct_complete_record_table {
			display: none;
			border-collapse: collapse;
		}
		#ct_complete_record_table.show {
			display: block;
		}
		#ct_complete_record_table tr {
			padding-bottom: 4px;
		}
		#ct_complete_record_table tr.hidden {
			display: none;
		}
		#ct_complete_record_table tr th {
			text-align: left;
			font-size: 14px;
			min-width: 100px;
			background: #eee;
		}
		#ct_complete_record_table tr td {
			vertical-align: top;
			font-size: 12px;
			border-bottom: 1px solid #ccc;
		}
		#ct_complete_record_table tr td.no-border {
			border-bottom: none;
		}
		#ct_complete_record_table tr td .indent {
			padding-left: 20px;
			font-style: italic;
		}
		#ct_complete_record_table table tr:has(td:last-child:empty) {
			display: none;
		}
	'
	);

	foreach ( $obj['units'] as $field ) {
		if ( isset( $field['type'] ) && isset( $field['value'] ) ) {
			if ( $field['type'] == 'spectrum/object_name' ) {
				$fields['spectrum/object_name'] = ( $fields['spectrum/object_name'] ?? '' ) . $field['value'] . '; ';
			} else {
				if ( isset( $fields[ $field['type'] ] ) ) {
					if ( is_array( $fields[ $field['type'] ] ) ) {
						$fields[ $field['type'] ][] = $field['value'];
					} else {
						$fields[ $field['type'] ] = array( $fields[ $field['type'] ], $field['value'] );
					}
				} else {
					$fields[ $field['type'] ] = $field['value'];
				}

				if ( isset( $field['units'] ) && is_array( $field['units'] ) ) {
					$i = 0;
					foreach ( $field['units'] as $sub_section ) {
						if ( isset( $sub_section['type'] ) && isset( $sub_section['value'] ) ) {
							$fields[ $sub_section['type'] ][ $i ] = $sub_section['value'];
							++$i;
						}
					}
				}
			}
		}
	}

	$html  = '<h5 id="ct_complete_record"><a href="#">' . esc_html__( 'View complete record', 'culture-object-display' ) . '</a></h5>';
	$html .= '<div id="ct_complete_record_table" style="display: none;"><table>';

	// Load field configuration from static PHP array for better performance.
	$field_config_file = plugin_dir_path( __FILE__ ) . 'spectrum-display-fields.php';
	if ( ! file_exists( $field_config_file ) ) {
		return $html . '<tr><td>' . esc_html__( 'Configuration file not found.', 'culture-object-display' ) . '</td></tr></table></div>';
	}

	$field_config = require $field_config_file;
	if ( ! is_array( $field_config ) ) {
		return $html . '<tr><td>' . esc_html__( 'Unable to load configuration file.', 'culture-object-display' ) . '</td></tr></table></div>';
	}

	$i               = 0;
	$display_section = false;
	$section         = '';

	foreach ( $field_config as $line ) {
		if ( $i == 0 ) {
			$html .= '<tr>';
			foreach ( $line as $cell ) {
				if ( is_string( $cell ) && ! empty( $cell ) ) {
					$html .= '<th>' . esc_html( $cell ) . '</th>';
				}
			}
			$html .= "</tr>\n";
		} else {
			$c       = 0;
			$new_row = false;

			foreach ( $line as $cell ) {
				if ( $c == 0 && ! empty( $cell ) ) {
					if ( $display_section ) {
						$html           .= $section;
						$display_section = false;
					}

					$section = '<tr><td colspan="3"><strong>' . esc_html( $cell ) . '</strong></td></tr>';
					$new_row = true;
				} elseif ( $new_row ) {
						$section .= '<tr><td class="no-border"></td><td>' . esc_html( $cell );
						$new_row  = false;
				} elseif ( $c == 3 ) {
						$field_key = $cell;
						$section  .= '<td>';
					if ( isset( $fields[ $field_key ] ) ) {
						if ( is_array( $fields[ $field_key ] ) ) {
							foreach ( $fields[ $field_key ] as $value ) {
								$section .= mds_make_links_clickable( $value ) . ' ';
							}
						} else {
							$section .= mds_make_links_clickable( $fields[ $field_key ] );
						}
						$display_section = true;
					}
						$section .= '</td></tr>';
				} else {
					if ( $c == 0 ) {
						$section .= '<tr><td class="no-border"></td>';
					}
					if ( $c == 1 ) {
						$section .= '<td>' . esc_html( $cell );
					}
					if ( $c == 2 ) {
						$section .= $cell ? '<span class="indent">' . esc_html( $cell ) . '</span></td>' : '</td>';
					}
				}

				$c = ( $c < 4 ) ? $c + 1 : 0;
			}
		}
		++$i;
	}

	// Add museum data service link if UID is available
	$museum_link = '';
	if ( $obj_admin && is_array( $obj_admin ) && isset( $obj_admin['uid'] ) && ! empty( $obj_admin['uid'] ) ) {
		$museum_link = '<p><a href="' . esc_url( 'https://museumdata.uk/object-search/object/?pid=' . rawurlencode( $obj_admin['uid'] ) ) . '">' . esc_html__( 'View this record along with millions of others from UK museums at the Museum Data Service.', 'culture-object-display' ) . '</a></p>';
	}

	$html .= '</table>' . $museum_link . '</div>';

	$html .= "<script>
		document.querySelector('#ct_complete_record a').addEventListener('click', function(e) {
			e.preventDefault();
			var table = document.getElementById('ct_complete_record_table');
			if (table.style.display === 'none' || table.style.display === '') {
				table.style.display = 'block';
				this.textContent = '" . esc_js( __( 'Hide complete record', 'culture-object-display' ) ) . "';
			} else {
				table.style.display = 'none';
				this.textContent = '" . esc_js( __( 'View complete record', 'culture-object-display' ) ) . "';
			}
		});
	</script>";

	return $html;
}
