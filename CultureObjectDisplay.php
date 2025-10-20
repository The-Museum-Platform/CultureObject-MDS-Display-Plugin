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
 * GitHub Branch: master
 * License: Apache 2 License
 */

require_once 'CultureObject/Display/COD.class.php';
register_activation_hook( __FILE__, array( 'CultureObject\Display\COD', 'check_versions' ) );
register_activation_hook( __FILE__, array( 'CultureObject\Display\COD', 'regenerate_permalinks' ) );
register_deactivation_hook( __FILE__, array( 'CultureObject\Display\COD', 'regenerate_permalinks' ) );
$cod = new \CultureObject\Display\COD();

function mds_cos_fields() {
	$id = get_the_ID();
	if ( ! $id ) {
		return false;
	}

	$obj       = cos_get_field( '@document' );
	$obj_admin = cos_get_field( '@admin' );

	function make_links_clickable( $text ) {
		return preg_replace( '!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a href="$1">$1</a>', $text );
	}

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
	$html .= '<h3 id="ct_complete_record"><a href="javascript:;">View complete record</a></h3>';
	$html .= "<div id=\"ct_complete_record_table\" class=\"\"><table>\n\n";

	$f = fopen( plugin_dir_path( __FILE__ ) . 'spectrum-display.csv', 'r' );

	$i              = 0;
	$displaySection = false;

	while ( ( $line = fgetcsv( $f ) ) !== false ) {
		if ( $i == 0 ) {
			$html .= '<tr>';
			foreach ( $line as $cell ) {
				if ( $cell ) {
					$html .= '<th>' . htmlspecialchars( $cell ) . '</th>';
				}
			}
			$html .= "</tr>\n";
		} else {
			$c      = 0;
			$row    = '';
			$newRow = false;

			foreach ( $line as $cell ) {
				if ( $c == 0 && $cell !== '' ) {
					if ( $displaySection ) {
						$html          .= $section;
						$displaySection = false;
					}

					$section = "<tr><td colspan='3'><strong>" . htmlspecialchars( $cell ) . '</strong></td></tr>';
					$newRow  = true;
				} elseif ( $newRow ) {
						$section .= "<tr><td class='no-border'></td><td>" . htmlspecialchars( $cell );
						$newRow   = false;
				} elseif ( $c == 3 ) {
						$field_key = $cell;
						$section  .= '<td>';
					if ( isset( $fields[ $field_key ] ) ) {
						if ( is_array( $fields[ $field_key ] ) ) {
							foreach ( $fields[ $field_key ] as $value ) {
								$section .= make_links_clickable( $value ) . ' ';
							}
						} else {
							$section .= make_links_clickable( $fields[ $field_key ] );
						}
						$displaySection = true;
					}
						$section .= '</td></tr>';
				} else {
					if ( $c == 0 ) {
						$section .= "<tr><td class='no-border'></td>";
					}
					if ( $c == 1 ) {
						$section .= '<td>' . htmlspecialchars( $cell );
					}
					if ( $c == 2 ) {
						$section .= $cell ? '<span class="indent">' . htmlspecialchars( $cell ) . '</span></td>' : '</td>';
					}
				}

				$c = ( $c < 4 ) ? $c + 1 : 0;
			}
		}
		++$i;
	}

	fclose( $f );

	$html .= "</table><p><a href=\"https://museumdata.uk/object-search/object/?pid={$obj_admin['uid']}\">View this record along with millions of others from UK museums at the Museum Data Service.</a></p></div><script type='text/javascript'>
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
