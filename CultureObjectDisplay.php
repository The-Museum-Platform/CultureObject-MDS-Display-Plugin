<?php
/**
 * Plugin Name: Culture Object Display
 * Plugin URI: http://cultureobject.co.uk
 * Description: An extension to Culture Object to provide an archive view of objects and single object view for all themes.
 * Version: 1.0.1-alpha3.002
 * Author: Liam Gladdy / Thirty8 Digital
 * Text Domain: culture-object-display
 * Author URI: https://github.com/lgladdy
 * GitHub Plugin URI: Thirty8Digital/CultureObjectDisplay
 * GitHub Branch: master
 * License: Apache 2 License
 */

require_once('CultureObject/Display/COD.class.php');
register_activation_hook(__FILE__, array('CultureObject\Display\COD', 'check_versions'));
register_activation_hook(__FILE__, array('CultureObject\Display\COD', 'regenerate_permalinks'));
register_deactivation_hook(__FILE__, array('CultureObject\Display\COD', 'regenerate_permalinks'));
$cod = new \CultureObject\Display\COD();

// NEW FUNCTION TO DISPLAY ALL FIELDS FOR RECORD
function mds_cos_fields() {
    $id = get_the_ID();
    if ( ! $id ) {
        return false;
    }
    
    // GET FULL RECORD FROM WP DATABASE
    $obj = cos_get_field('@document');
    $obj_admin = cos_get_field('@admin');
    
    // HELPER FUNCTION TO RETURN CLICKABLE LINK
    function make_links_clickable($text){
        return preg_replace('!(((f|ht)tp(s)?://)[-a-zA-Zа-яА-Я()0-9@:%_+.~#?&;//=]+)!i', '<a href="$1">$1</a>', $text);
    }
    
    // CREATE NEW ARRAY TO HOLD RECORD DATA
    $fields = array();

    // LOOP THROUGH OBJECT AND ADD DATA TO $fields ARRAY
    foreach ( $obj['units'] as $field ) {
        // COMBINE NAMES INTO SINGLE FIELD
        if($field['type']=='spectrum/object_name'){
            $fields['spectrum/object_name'] .= $field['value'].'; ';
        }else{
            // IF FIELD WITH SAME NAME EXISTS CREATE SUB ARRAY OF DATA
            if($fields[$field['type']]){
                if(is_array($fields[$field['type']])){
                    $new_value = array_push($fields[$field['type']], $field['value']);
                }else{
                    $new_value = array($fields[$field['type']], $field['value']);
                }
                $fields[$field['type']] = $new_value;
            }else{
                // ADD SINGLE ITEM TO ARRAY
                $fields[$field['type']] = $field['value'];
            }
            
            // ADD CHILD DATA OF ITEM TO ARRAY
            $i = 0;
            foreach($field['units'] as $sub_section){
                $fields[$sub_section['type']][$i] = $sub_section['value'];
                $i++;
            }
        }
    }

    // CREATE HTML OUTPUT WITH BASIC CSS FOR TABLE
    $html = "<style>#ct_complete_record_table{display:none;border-collapse: collapse;}#ct_complete_record_table.show{display:block;}#ct_complete_record_table tr{padding-bottom:4px;}#ct_complete_record_table tr.hidden{display:none;}#ct_complete_record_table tr th{text-align:left;font-size:14px;min-width:100px;background:#eee;}#ct_complete_record_table tr td{vertical-align:top;font-size:12px;border-bottom:1px solid #ccc;}#ct_complete_record_table tr td.no-border{border-bottom:none;}#ct_complete_record_table tr td .indent{padding-left:20px;font-style:italic;}#ct_complete_record_table table tr:has(td:last-child:empty) { display: none; }</style>";
    
    // OUTPUT CLICKABLE LINK TO SHOW/HIDE FULL RECORD
    $html .= '<h3 id="ct_complete_record"><a href="javascript:;">View complete record</a></h3>';
    
    // OUTPUT CONTAINER DIV AND START OF TABLE
    $html .= "<div id=\"ct_complete_record_table\" class=\"\"><table>\n\n";
    
    // OPEN CSV TEMPLATE FILE
    $f = fopen(plugin_dir_url( __FILE__ )."spectrum-display.csv", "r");
    
    // FLAGS
    $i = 0; // ROW NUMBER
    $displaySection = false; // WHETHER TO INCLUDE SECTION HTML IN OUTPUT
    
    // LOOP THROUGH CSV
    while (($line = fgetcsv($f)) !== false) {
        
        // CREATE TABLE HEADINGS
        if($i==0){
            $html .= "<tr>";
            foreach ($line as $cell) {
                if($cell){
                    $html .= "<th>" . htmlspecialchars($cell) . "</th>";	
                }
            }
            $html .= "</tr>\n";
        }else{
            
            // CREATE ROW
            
            // FLAGS
            $c = 0; // CELL NUMBER
            $row = ''; // ROW HTML
            
            foreach ($line as $cell) {
                
                if($c==0 AND $cell!==''){
                    
                    // IF FIRST CELL CONTAINS DATA AND PREVIOUS SECTION IS TO BE DISPLAYED
                    if($displaySection){
                        $html .= $section;
                        $displaySection = false;
                    }
                    
                    // NEW SECTION WITH COLSPAN HEADING
                    $section = '';
                    $section .= "<tr><td colspan='3'><strong>" . htmlspecialchars($cell) . "</strong></td></tr>";
                    $newRow = true;
                }else{
                    if($newRow){
                        // CREATE ROW WITH EMPTY FIRST CELL
                        $section .= "<tr><td class='no-border'></td><td>" . htmlspecialchars($cell);
                        $newRow = false;
                    }else{
                        if($c==3){
                            // GET DATA FROM $fields ARRAY AND INCLUDE IN TABLE
                            $string = explode('spectrum/', $cell);
                            if(is_array($fields[$cell])){
                                $section .= "<td>";
                                foreach($fields[$cell] as $value){
                                    $section .= $value." ";
                                }
                                $section .= "</td></tr>";
                            }else{
                                $section .= "<td>" . make_links_clickable($fields[$cell]) . "</td></tr>";	
                            }	
                            // SET $displaySection FLAG TO TRUE AS DATA EXISTS
                            if($fields[$cell]){
                                $displaySection = true;
                            }
                        }else{
                            if($c==0){
                                // CREATE EMPTY FIRST CELL
                                $section .= "<tr><td class='no-border'></td>";
                            }
                            if($c==1){
                                // CREATE SUB HEADING
                                $section .= "<td>" . htmlspecialchars($cell);
                            }
                            if($c==2){
                                // CREATE INDENTED HEADING
                                if($cell){
                                    $section .= '<span class="indent">' . htmlspecialchars($cell)."</span></td>";	
                                }else{
                                    $section .= "</td>";
                                }
                            }
                        }
                    }
                }
                
                // RESET CELL COUNT AT END OF ROW
                if($c < 4){
                    $c++;
                }else{
                    $c = 0;
                }
                
            }
        }
        $i++;
    }
    
    // CLOSE CSV FILE
    fclose($f);
    
    // ADD END OF TALE AND SHOW/HIDE JAVASCRIPT TO HTML
    $html .= "</table><p><a href=\"https://museumdata.uk/object-search/object/?pid=$obj_admin[uid]\">View this record along with millions of others from UK museums at the Museum Data Service.</a></p></div><script type='text/javascript'>
    document.addEventListener('DOMContentLoaded', () => {const button = document.querySelector('#ct_complete_record');const elementToToggle = document.getElementById('ct_complete_record_table');button.addEventListener('mousedown', () => {elementToToggle.classList.toggle('show');});});
    </script>";
    
    // RETURN HTML
    return $html;
}