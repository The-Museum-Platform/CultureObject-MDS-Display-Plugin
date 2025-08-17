<?php
/**
 * Plugin Name: Culture Object MDS Display
 * Plugin URI: http://cultureobject.co.uk
 * Description: An extension to Culture Object to provide an archive view of objects and single object view for all themes.
 * Version: 1.0.2
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

function mds_cos_fields() {
	$id = get_the_ID();
	if ( ! $id ) {
		return false;
	}

	$obj       = cos_get_field( '@document' );
	$obj_admin = cos_get_field( '@admin' );

	$fields = array();

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

	$html  = '<style>#ct_complete_record_table{display:none;border-collapse: collapse;}#ct_complete_record_table.show{display:block;}#ct_complete_record_table tr{padding-bottom:4px;}#ct_complete_record_table tr.hidden{display:none;}#ct_complete_record_table tr th{text-align:left;font-size:14px;min-width:100px;background:#eee;}#ct_complete_record_table tr td{vertical-align:top;font-size:12px;border-bottom:1px solid #ccc;}#ct_complete_record_table tr td.no-border{border-bottom:none;}#ct_complete_record_table tr td .indent{padding-left:20px;font-style:italic;}#ct_complete_record_table table tr:has(td:last-child:empty) { display: none; }</style>';
	$html .= '<h3 id="ct_complete_record"><a href="#">View complete record</a></h3>';
	$html .= '<div id="ct_complete_record_table"><table>\n\n';

	$csv_file = plugin_dir_path( __FILE__ ) . 'spectrum-display.csv';
	if ( ! file_exists( $csv_file ) ) {
		return $html . '<tr><td>Configuration file not found.</td></tr></table></div>';
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	$f = fopen( $csv_file, 'r' );
	if ( ! $f ) {
		return $html . '<tr><td>Unable to read configuration file.</td></tr></table></div>';
	}

	$i               = 0;
	$display_section = false;
	$section         = '';

	$line = fgetcsv( $f );
	while ( false !== $line ) {
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
		$line = fgetcsv( $f );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	fclose( $f );

	$html .= '</table><p><a href="' . esc_url( 'https://museumdata.uk/object-search/object/?pid=' . rawurlencode( $obj_admin['uid'] ) ) . "\">View this record along with millions of others from UK museums at the Museum Data Service.</a></p></div><script type='text/javascript'>
    document.addEventListener('DOMContentLoaded', () => {
        const button = document.querySelector('#ct_complete_record');
        const elementToToggle = document.getElementById('ct_complete_record_table');
        const link = button.querySelector('a');
        button.addEventListener('mousedown', () => {
            elementToToggle.classList.toggle('show');
            if (elementToToggle.classList.contains('show')) {
                link.textContent = 'Hide complete record';
            } else {
                link.textContent = 'View complete record';
            }
        });
    });
    </script>";

	return $html;
}
