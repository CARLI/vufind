<?php

namespace CARLI\ILS\Driver;

use File_MARC, Yajra\Pdo\Oci8, PDO, PDOException, VuFind\Exception\ILS as ILSException;
use VuFind\Config\Locator as ConfigLocator;
use Zend\Validator\EmailAddress as EmailAddressValidator;

class VoyagerRestful extends \VuFind\ILS\Driver\VoyagerRestful
{
    /**
     * Protected support method for getHolding.
     *
     * @param array $recordSegment A Marc Record Segment obtained from an SQL query
     *
     * @return array Keyed data
     */
    protected function processRecordSegment($recordSegment)
    {
        $marcDetails = [];

        try {
            $marc = new File_MARC(
                str_replace(["\n", "\r"], '', $recordSegment),
                File_MARC::SOURCE_STRING
            );
            if ($record = $marc->next()) {
                // Get Notes
                $data = $this->getMFHDData(
                    $record,
                    isset($this->config['Holdings']['notes'])
                    ? $this->config['Holdings']['notes']
                    : '852z'
                );
                if ($data) {
                    $marcDetails['notes'] = $data;
                }

                $data = $this->getValidatedMFHDData(
                    $record,
                    /* CARLI edit: hard-code the 866az!
                    isset($this->config['Holdings']['summary'])
                    ? $this->config['Holdings']['summary']
                    : '866a',
                    */
                    '866az',
                    function($field_) {
                        // these need to be suppressed
                        $ind2 = $field_->getIndicator(2);
                        if ($ind2 == '2' || $ind2 == '3') {
                            return false;
                        }
                        return true;
                    },
                    null
                );

                $data = array_merge($data ? (is_array($data) ? $data : [ $data ]) : [], $this->mfhd_library_has_volumes($record, '853', '863')); // CARLI added
                if ($data) {
                    $marcDetails['summary'] = $data;
                }

                // Get Supplements
                if (isset($this->config['Holdings']['supplements'])) {
                    $data = $this->getMFHDData(
                        $record,
                        $this->config['Holdings']['supplements']
                    );
                    $data = array_merge($data ? (is_array($data) ? $data : [ $data ]) : [], $this->mfhd_library_has_volumes($record, '854', '864')); // CARLI added
                    if ($data) {
                        $marcDetails['supplements'] = $data;
                    }
                }

                // Get Indexes
                if (isset($this->config['Holdings']['indexes'])) {
                    $data = $this->getMFHDData(
                        $record,
                        $this->config['Holdings']['indexes']
                    );
                    $data = array_merge($data ? (is_array($data) ? $data : [ $data ]) : [], $this->mfhd_library_has_volumes($record, '855', '865')); // CARLI added
                    if ($data) {
                        $marcDetails['indexes'] = $data;
                    }
                }
            }
        } catch (\Exception $e) {
            trigger_error(
                'Poorly Formatted MFHD Record', E_USER_NOTICE
            );
        }
        return $marcDetails;
    }


///////////////////////////////////////////////////////////////////////////////////////////////////////////
    protected function getValidatedMFHDData($record, $fieldSpecs, $validateFieldFunc, $validateSubfieldFunc)
    {
        if (!is_array($fieldSpecs)) {
            $fieldSpecs = explode(':', $fieldSpecs);
        }
        $results = '';
        foreach ($fieldSpecs as $fieldSpec) {
            $fieldCode = substr($fieldSpec, 0, 3);
            $subfieldCodes = substr($fieldSpec, 3);
            if ($fields = $record->getFields($fieldCode)) {
                foreach ($fields as $field) {

                    if ($validateFieldFunc) {
                        if (! $validateFieldFunc($field)) {
                            continue;
                        }
                    }

                    if ($subfields = $field->getSubfields()) {
                        $line = '';
                        foreach ($subfields as $code => $subfield) {
                            if (!strstr($subfieldCodes, $code)) {
                                continue;
                            }

                            if ($validateSubfieldFunc) {
                                if (! $validateSubfieldFunc($code, $subfield)) {
                                    continue;
                                }
                            }

                            if ($line) {
                                $line .= ' ';
                            }
                            $line .= $subfield->getData();
                        }
                        if ($line) {
                            if (!$results) {
                                $results = $line;
                            } else {
                                if (!is_array($results)) {
                                    $results = [$results];
                                }
                                $results[] = $line;
                            }
                        }
                    }
                }
            }
        }
        return $results;
    }

    protected function library_has_volumes_process_default($captions, $values, $fields)
    {
        $parts = array();
        $must_have_caption = array(
            'a' => 1,
            'b' => 1,
            'c' => 1,
            'd' => 1,
            'e' => 1,
            'f' => 1,
            'g' => 1,
            'h' => 1,
            'i' => 1,
            'j' => 1,
            'k' => 1,
            'l' => 1,
            'm' => 1,
            't' => 1,
        );
        $len = count($captions);
        for ($i=0; $i<$len; $i++) {
            if ($values[$i]) {
                if ($must_have_caption[$fields[$i]] && ! $captions[$i]) {
                    continue;
                }
                $ret = '';
                if ($fields[$i] == 'g') {
                    $ret .= '= ';
                }
                if (!preg_match('/^\(.*\)$/', $captions[$i])) {
                    if ($fields[$i] != 'o') {
                        $ret .= $captions[$i];
                    }
                }
               if ($captions[$i] != '' && !preg_match('/\.$/', $captions[$i])) {
                  if ($fields[$i] != 'o') {
                     $ret .= ' ';
                  }
               }
               $val = $values[$i];
               if ($fields[$i] == 'o') {
                  $val = '"' . $val . '"';
               }
               $ret .= $val;
               $parts[] = $ret;
            }
        }
        return $parts;
    }
    protected function library_has_volumes_process_enum($captions, $values, $fields)
    {
        $parts = array();

        $is_range = 0;
        $enum1 = '';
        $enum2 = '';

        $cap = $captions[0];
        if (preg_match('/^\(.*\)$/', $cap)) {
            $cap = '';
        } else {
            if (!preg_match('/\.$/', $cap)) {
                $cap .= ' ';
            }
        }

        $no_left = count($values);

        # is this a range?
        if ($no_left > 1 && preg_match('/\d+-\d+/', $values[1]) && preg_match('/(\d+)-(\d+)/', $values[0], $matches)) {
            $enum1 = $cap . $matches[1];
            $enum2 = $cap . $matches[2];
            $is_range = 1;
        } else {
            $enum1 = $cap . $values[0];
        }

        $len = count($captions);
        for ($i=1; $i<$len; $i++) {

            # is this subpart a range too?
            if (preg_match('/(\d+)-(\d+)/', $values[$i], $matches)) {
                $val1 = $matches[1];
                $val2 = $matches[2];

                $cap = $captions[$i];
                if (preg_match('/^\(.*\)$/', $cap)) {
                    $cap = '';
                } else {
                    if (!preg_match('/\.$/', $cap)) {
                        $cap .= ' ';
                    }
                }
                if ($fields[$i] == 'g') {
                    $cap = '= ' . $cap;
                }

                if ($is_range) {
                    if ($enum1) {
                        $enum1 .= ':';
                    }
                    $enum1 .= $cap . $val1;
                    if ($enum2) {
                        $enum2 .= ':';
                    }
                    $enum2 .= $cap . $val2;
                } else {
                    if ($enum1) {
                        $enum1 .= ':';
                    }
                    $enum1 .= $cap . $val1 . '-' . $val2;
                }

            } else {
                $val = $values[$i];

                $cap = $captions[$i];
                if (preg_match('/^\(.*\)$/', $cap)) {
                    $cap = '';
                } else {
                    if (!preg_match('/\.$/', $cap)) {
                        $cap .= ' ';
                    }
                }
                if ($fields[$i] == 'g') {
                    $cap = '= ' . $cap;
                }
                $val =  $cap . $val;

                if ($enum1) {
                    $enum1 .= ':';
                }
                $enum1 .= $val;
                if ($is_range) {
                    if ($enum2) {
                        $enum2 .= ':';
                    }
                    $enum2 .= $val;
                }
            }

        }

        $ret = $enum1;
        $parts[] = $enum1;
        if ($is_range) {
            $parts[] = $enum2;
        }

        return $parts;
    }
    protected function xlate_chrono_value($caption, $value)
    {
        $new_value = $value;
        if ($caption == '(month)' || $caption == '(month/month)') {
            if ($value == '01') {
                $new_value = 'Jan.';
            } else if($value == '02') {
                $new_value = 'Feb.';
            } else if($value == '03') {
                 $new_value = 'Mar.';
            } else if($value == '04') {
                 $new_value = 'Apr.';
            } else if($value == '05') {
                 $new_value = 'May';
            } else if($value == '06') {
                 $new_value = 'June';
            } else if($value == '07') {
                 $new_value = 'July';
            } else if($value == '08') {
                 $new_value = 'Aug.';
            } else if($value == '09') {
                 $new_value = 'Sept.';
            } else if($value == '10') {
                 $new_value = 'Oct.';
            } else if($value == '11') {
                 $new_value = 'Nov.';
            } else if($value == '12') {
                 $new_value = 'Dec.';
            }
        } else if ($caption == '(season)') {
            if ($value == '21') {
                $new_value = 'Spring';
            } else if ($value == '22') {
                $new_value = 'Summer';
            } else if ($value == '23') {
                $new_value = 'Autumn';
            } else if ($value == '24') {
                $new_value = 'Winter';
            }
        }
        return $new_value;
    }
    protected function library_has_volumes_process_chrono($captions, $values, $fields)
    {
        $parts = array();

        $is_range = 0;
        $date1 = '';
        $date2 = '';

        $no_left = count($values);

        # is this a range?
        if ($no_left > 1 && preg_match('/(\d+)-(\d+)/', $values[0], $matches)) {
            $date1 = $matches[1];
            $date2 = $matches[2];
            $is_range = 1;
        } else {
            $date1 = $values[0];
        }
        $date1 =  $this->xlate_chrono_value($captions[0], $date1);
        if ($is_range) {
            $date2 =  $this->xlate_chrono_value($captions[0], $date2);
        }

        $len = count($captions);
        for ($i=1; $i<$len; $i++) {

            $colon_or_space = ':';
            if ($captions[$i-1] == '(month)'
             && $captions[$i] == '(day)' ) {
               $colon_or_space = ' ';
            }

            # is this subpart a range too?
            if (preg_match('/(\d+)-(\d+)/', $values[$i], $matches)) {
                $val1 = $matches[1];
                $val2 = $matches[2];
                $val1 = $this->xlate_chrono_value($captions[$i], $val1);
                $val2 = $this->xlate_chrono_value($captions[$i], $val2);

                if ($is_range) {
                    if ($date1) {
                        $date1 .= $colon_or_space;
                    }
                    $date1 .= $val1;
                    if ($date2) {
                        $date2 .= $colon_or_space;
                    }
                    $date2 .= $val2;
                } else {
                    if ($date1) {
                        $date1 .= $colon_or_space;
                    }
                    $date1 .= $val1 . '-' . $val2;
                }

            } else {
                $val = $values[$i];
                $val = $this->xlate_chrono_value($captions[$i], $val);
                if ($date1) {
                    $date1 .= $colon_or_space;
                }
                $date1 .= $val;
                if ($is_range) {
                    if ($date2) {
                        $date2 .= $colon_or_space;
                    }
                    $date2 .= $val;
                }
            }

        }

        $ret = $date1;
        $parts[] = $date1;
        if ($is_range) {
            $parts[] = $date2;
        }

        return $parts;
     }

    protected function mfhd_library_has_volumes($m_, $pair1, $pair2)
    {
        $subfields = array('a','b','c','d','e','f','g','h','i','j','k','l','m','o','t','z');

        $display = array();
        $fields_ = $m_->getFields($pair1);
        foreach ($fields_ as $field_) {
            $link_ = $field_->getSubfield('8');
            if ($link_) {
                $link = $link_->getData();
                foreach ($subfields as $sf) {
                    $sf_ = $field_->getSubfield($sf);
                    if ($sf_) {
                        $sfv = $sf_->getData();
                        $display[$link][$sf]['caption'] = $sfv;
                    }
                }
            }
        }

        $sort_data = array();
        $fields_ = $m_->getFields($pair2);
        foreach ($fields_ as $field_) {

            // these need to be suppressed
            $ind2 = $field_->getIndicator(2);
            if ($ind2 == '2' || $ind2 == '3') {
                continue;
            }

            $eight_ = $field_->getSubfield('8');
            if ($eight_) {
                $eight = $eight_->getData();
                $linkSeq = explode ('.', $eight, 2);
                $link = $linkSeq[0];
                $seq = $linkSeq[1];
                if (array_key_exists($link, $display)) {
                    foreach ($subfields as $sf) {
                        if (array_key_exists($sf, $display[$link])) {
                           $sf_ = $field_->getSubfield($sf);
                           if ($sf_) {
                                $sfv = $sf_->getData();
                                $sort_data[$link][$seq][$sf]['caption'] = $display[$link][$sf]['caption'];
                                $sort_data[$link][$seq][$sf]['value'] = $sfv;
                            }
                        }
                    }
                }
            }
         }


         $all_data = array();

         $sort_data_links = array_keys($sort_data);
         usort($sort_data_links, function($a, $b) { return strcmp($a, $b); });
         foreach ($sort_data_links as $link) {
             $sort_data_seqs = array_keys($sort_data[$link]);
             usort($sort_data_seqs, function($a, $b) { return strcmp($a, $b); });

             foreach ($sort_data_seqs as $seq) {
                 $disp = '';

                 $this_volume = array();
                 $enum = array();

                 $these_subfields = array();
                 $sort_data_link_seq_sfs = array_keys($sort_data[$link][$seq]);
                 usort($sort_data_link_seq_sfs, function($a, $b) { return strcmp($a, $b); });
                 foreach ($sort_data_link_seq_sfs as $sf) {
                    $these_subfields[$sf] = 1;
                 }

                 $o_caption = '';
                 if (array_key_exists('o', $sort_data[$link][$seq])) {
                     $o_caption = $sort_data[$link][$seq]['o']['caption'];
                     if ($o_caption) {
                         $o_caption = '"' . $o_caption . '" ';
                     }
                 }

                 $chrons = array( '(year)' => 1,
                                '(month)' => 1,
                                '(month/month)' => 1,
                                '(season)' => 1,
                                '(day)' => 1,
                              );

                 $a_d_fields = array();
                 $a_d_captions = array();
                 $a_d_values = array();
                 $a_d_is_chrono = -1; # empty
                 $i = 0;
                 foreach (['a','b','c','d'] as $a_d) {
                    if (array_key_exists($a_d, $these_subfields)) {
                       if (! array_key_exists('value', $sort_data[$link][$seq][$a_d])) {
                           continue;
                       }
                       $value = $sort_data[$link][$seq][$a_d]['value'];
                       if (! $value) {
                           continue;
                       }
                       if (! array_key_exists('caption', $sort_data[$link][$seq][$a_d])) {
                           continue;
                       }
                       $caption = $sort_data[$link][$seq][$a_d]['caption'];

                       $a_d_fields[$i] = $a_d;
                       $a_d_captions[$i] = $caption;
                       $a_d_values[$i++] = $value;
                       if (array_key_exists($caption, $chrons)) {
                          $a_d_is_chrono = 1;
                       } else {
                          $a_d_is_chrono = 0;
                       }
                    }
                 }

                 $a_d_parts = array();
                 if ($a_d_is_chrono == 1) {
                    $a_d_parts = $this->library_has_volumes_process_chrono($a_d_captions, $a_d_values, $a_d_fields);
                 } else if ($a_d_is_chrono == 0) {
                    $a_d_parts = $this->library_has_volumes_process_enum($a_d_captions, $a_d_values, $a_d_fields);
                 }

                 $e_h_fields = array();
                 $e_h_captions = array();
                 $e_h_values = array();
                 $e_h_is_there = -1; # empty
                 $i = 0;
                 foreach (['e','f','g','h'] as $e_h) {
                    if (array_key_exists($e_h, $these_subfields)) {
                       $e_h_is_there = 1;

                       if (! array_key_exists('value', $sort_data[$link][$seq][$e_h])) {
                           continue;
                       }
                       $value = $sort_data[$link][$seq][$e_h]['value'];
                       if (! $value) {
                           continue;
                       }
                       if (! array_key_exists('caption', $sort_data[$link][$seq][$e_h])) {
                           continue;
                       }
                       $caption = $sort_data[$link][$seq][$e_h]['caption'];

                       $e_h_fields[$i] = $e_h;
                       $e_h_captions[$i] = $caption;
                       $e_h_values[$i++] = $value;
                    }
                 }

                 $e_h_parts = array();
                 if ($e_h_is_there == 1) {
                    $e_h_parts = $this->library_has_volumes_process_default($e_h_captions, $e_h_values, $e_h_fields);
                 }

                 $i_l_fields = array();
                 $i_l_captions = array();
                 $i_l_values = array();
                 $i_l_is_chrono = -1; # empty
                 $i = 0;
                 foreach (['i','j','k','l'] as $i_l) {
                    if (array_key_exists($i_l, $these_subfields)) {
                       if (! array_key_exists('value', $sort_data[$link][$seq][$i_l])) {
                           continue;
                       }
                       $value = $sort_data[$link][$seq][$i_l]['value'];
                       if (! $value) {
                           continue;
                       }
                       if (! array_key_exists('caption', $sort_data[$link][$seq][$i_l])) {
                           continue;
                       }
                       $caption = $sort_data[$link][$seq][$i_l]['caption'];
                       $i_l_fields[$i] = $i_l;
                       $i_l_captions[$i] = $caption;
                       $i_l_values[$i++] = $value;
                       if (array_key_exists($caption, $chrons)) {
                          $i_l_is_chrono = 1;
                       } else {
                          $i_l_is_chrono = 0;
                       }
                    }
                 }

                 $i_l_parts = array();
                 if ($i_l_is_chrono == 1) {
                    $i_l_parts = $this->library_has_volumes_process_chrono($i_l_captions, $i_l_values, $i_l_fields);
                 } else if ($i_l_is_chrono == 0) {
                    $i_l_parts = $this->library_has_volumes_process_enum($i_l_captions, $i_l_values, $i_l_fields);
                 }

                 $m_z_fields = array();
                 $m_z_captions = array();
                 $m_z_values = array();
                 $m_z_is_there = -1; # empty
                 $i = 0;
                 foreach (['m','o','t','z'] as $m_z) {
                    if (array_key_exists($m_z, $these_subfields)) {
                       $m_z_is_there = 1;

                       if (! array_key_exists('value', $sort_data[$link][$seq][$m_z])) {
                           continue;
                       }
                       $value = $sort_data[$link][$seq][$m_z]['value'];
                       if (! $value) {
                           continue;
                       }
                       if (! array_key_exists('caption', $sort_data[$link][$seq][$m_z])) {
                           continue;
                       }
                       $caption = $sort_data[$link][$seq][$m_z]['caption'];
                       $m_z_fields[$i] = $m_z;
                       $m_z_captions[$i] = $caption;
                       $m_z_values[$i++] = $value;
                    }
                 }

                 $m_z_parts = array();
                 if ($m_z_is_there == 1) {
                    $m_z_parts = $this->library_has_volumes_process_default($m_z_captions, $m_z_values, $m_z_fields);
                 }

                 $e_h_parts_str = implode (" ", $e_h_parts);
                 if ($e_h_parts_str) {
                     $e_h_parts_str = ' '. $e_h_parts_str;
                 }
                 $m_z_parts_str = implode (" ", $m_z_parts);
                 if ($m_z_parts_str) {
                     $m_z_parts_str = ' '. $m_z_parts_str;
                 }
                 if (count($a_d_parts) > 1) {
                    if (count($i_l_parts) > 1) {
                       $disp .= $o_caption . $a_d_parts[0] . ' (' . $i_l_parts[0] . ')' . '-' . $a_d_parts[1] . ' (' . $i_l_parts[1] . ')' . $e_h_parts_str . $m_z_parts_str;
                    } else if (count($i_l_parts) > 1) {
                       $disp .= $o_caption . $a_d_parts[0] . ' (' . $i_l_parts[0] . ')' . '-' . $a_d_parts[1] . ' (' . $i_l_parts[0] . ')' . $e_h_parts_str . $m_z_parts_str;
                    } else {
                       $disp .= $o_caption . $a_d_parts[0] . '-' . $a_d_parts[1] . $e_h_parts_str . $m_z_parts_str;
                    }
                 }  else if (count($a_d_parts) > 0) {
                    if (count($i_l_parts) > 1) {
                       $disp .= $o_caption . $a_d_parts[0] . ' (' . implode("-", $i_l_parts) . ')' . $e_h_parts_str . $m_z_parts_str;
                    } else if (count($i_l_parts) > 0) {
                       $disp .= $o_caption . $a_d_parts[0] . ' (' . $i_l_parts[0] . ')' . $e_h_parts_str . $m_z_parts_str;
                    } else {
                       $disp .= $o_caption . $a_d_parts[0] . $e_h_parts_str . $m_z_parts_str;
                    }
                 }

                 $all_data[] = $disp;
             }
         }
         return $all_data;
    }
///////////////////////////////////////////////////////////////////////////////////////////////////////////

    protected function getPurchaseHistoryData($id)
    {
        $sql = "select LINE_ITEM_COPY_STATUS.MFHD_ID, SERIAL_ISSUES.ENUMCHRON " .
               "from $this->dbName.SERIAL_ISSUES, $this->dbName.COMPONENT, " .
               "$this->dbName.ISSUES_RECEIVED, $this->dbName.SUBSCRIPTION, " .
               "$this->dbName.LINE_ITEM, $this->dbName.LINE_ITEM_COPY_STATUS " .
               ", $this->dbName.MFHD_MASTER " . # CARLI ADDED
               "where SERIAL_ISSUES.COMPONENT_ID = COMPONENT.COMPONENT_ID " .
               "and ISSUES_RECEIVED.ISSUE_ID = SERIAL_ISSUES.ISSUE_ID " .
               "and ISSUES_RECEIVED.COMPONENT_ID = COMPONENT.COMPONENT_ID " .
               "and COMPONENT.SUBSCRIPTION_ID = SUBSCRIPTION.SUBSCRIPTION_ID " .
               "and SUBSCRIPTION.LINE_ITEM_ID = LINE_ITEM.LINE_ITEM_ID " .
               "and LINE_ITEM_COPY_STATUS.LINE_ITEM_ID = LINE_ITEM.LINE_ITEM_ID " .
               "and LINE_ITEM_COPY_STATUS.MFHD_ID = MFHD_MASTER.MFHD_ID " . # CARLI ADDED
               "and MFHD_MASTER.SUPPRESS_IN_OPAC = 'N'  " . # CARLI ADDED
               "and SERIAL_ISSUES.RECEIVED > 0 " .
               "and ISSUES_RECEIVED.OPAC_SUPPRESSED = 1 " .
               "and LINE_ITEM.BIB_ID = :id " .
               "order by LINE_ITEM_COPY_STATUS.MFHD_ID, SERIAL_ISSUES.ISSUE_ID DESC";
        try {
            $sqlStmt = $this->executeSQL($sql, [':id' => $id]);
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
        $raw = $processed = [];
        // Collect raw data:
        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
            $raw[] = $row['MFHD_ID'] . '||' . utf8_encode($row['ENUMCHRON']);
        }
        // Deduplicate data and format it:
        foreach (array_unique($raw) as $current) {
            list($holdings_id, $issue) = explode('||', $current, 2);
            $processed[] = compact('issue', 'holdings_id');
        }
        return $processed;
    }

    protected function getHoldingEItemsSQL($id)
    {
        // Expressions
        $sqlExpressions = [
            "BIB_MFHD.BIB_ID",
            "BIB_MFHD.MFHD_ID",
            "EITEM.LINK as EITEM_LINK",
            "EITEM.CAPTION as EITEM_CAPTION",
            "'No barcode' as ITEM_BARCODE",
            "EITEM.EITEM_ID as ITEM_ID",
            "null as ON_RESERVE",
            "EITEM.SEQUENCE as ITEM_SEQUENCE_NUMBER",
            "0 as RECALLS_PLACED",
            "0 as HOLDS_PLACED",
            "null as status",
            "MFHD_DATA.RECORD_SEGMENT",
            "EITEM.ENUMERATION as ITEM_ENUM",
            "null as CHRON",
            "NVL(LOCATION.LOCATION_DISPLAY_NAME, LOCATION.LOCATION_NAME) as location",
            "null as TEMP_LOCATION",
            "null as PERM_LOCATION",
            "MFHD_MASTER.DISPLAY_CALL_NO as callnumber",
            "null as duedate",
            "null as RETURNDATE",
            "0 as SORT_SEQ",
            "null as ITEM_TYPE_ID",
            "null as TEMP_ITEM_TYPE_ID ",
        ];

        // From
        $sqlFrom = [
            $this->dbName . ".BIB_MFHD",
            $this->dbName . ".EITEM",
            $this->dbName . ".MFHD_MASTER",
            $this->dbName . ".MFHD_DATA",
            $this->dbName . ".LOCATION",
        ];

        // Where
        $sqlWhere = [
            "BIB_MFHD.BIB_ID = :id",
            "BIB_MFHD.MFHD_ID = EITEM.MFHD_ID",
            "MFHD_MASTER.MFHD_ID = BIB_MFHD.MFHD_ID",
            "MFHD_MASTER.LOCATION_ID = LOCATION.LOCATION_ID",
            "MFHD_DATA.MFHD_ID = BIB_MFHD.MFHD_ID",
            "MFHD_MASTER.SUPPRESS_IN_OPAC='N'",
        ];

        // Order
        $sqlOrder = ["ITEM_SEQUENCE_NUMBER", "MFHD_DATA.MFHD_ID", "MFHD_DATA.SEQNUM"];

        // Bind
        $sqlBind = [':id' => $id];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'order' => $sqlOrder,
            'bind' => $sqlBind,
        ];

        return $sqlArray;
    }

    protected function getHoldingItemsSQL($id)
    {
        // Expressions
        $sqlExpressions = [
            "BIB_MASTER.BIB_ID",
            "MFHD_ITEM.MFHD_ID",
            "ITEM.ITEM_ID",
            "NVL(ITEM_BARCODE.ITEM_BARCODE, 'No barcode') as ITEM_BARCODE",
            "ITEM.ON_RESERVE",
            "ITEM.ITEM_SEQUENCE_NUMBER",
            "ITEM.RECALLS_PLACED",
            "ITEM.HOLDS_PLACED",
            "ITEM_STATUS_TYPE.ITEM_STATUS_DESC as status",
            "MFHD_DATA.RECORD_SEGMENT",
            "MFHD_ITEM.ITEM_ENUM", 
            "MFHD_ITEM.CHRON",
            "NVL(LOCATION.LOCATION_DISPLAY_NAME, LOCATION.LOCATION_NAME) as location",
            "ITEM.TEMP_LOCATION", 
            "ITEM.PERM_LOCATION", 
            "MFHD_MASTER.DISPLAY_CALL_NO as callnumber",
            "to_char(CIRC_TRANSACTIONS.CURRENT_DUE_DATE, 'MM-DD-YY') as duedate",
/*** 
 ***
 RETURNDATE is not always correct (and/or confusing at times); comment it out: 

            "(SELECT TO_CHAR(MAX(CIRC_TRANS_ARCHIVE.DISCHARGE_DATE), 'MM-DD-YY HH24:MI') " .
                "FROM " . $this->dbName . ".CIRC_TRANS_ARCHIVE " . 
                "WHERE CIRC_TRANS_ARCHIVE.ITEM_ID = ITEM.ITEM_ID) as RETURNDATE",
 ***
 ***/
            "null as RETURNDATE",
            "ITEM.ITEM_SEQUENCE_NUMBER",
            "(SELECT SORT_GROUP_LOCATION.SEQUENCE_NUMBER " .
                "FROM " . $this->dbName . ".SORT_GROUP, " . $this->dbName . ".SORT_GROUP_LOCATION " .
                "WHERE SORT_GROUP.SORT_GROUP_DEFAULT = 'Y' " .
                "AND SORT_GROUP_LOCATION.SORT_GROUP_ID = SORT_GROUP.SORT_GROUP_ID " .
                "AND SORT_GROUP_LOCATION.LOCATION_ID = ITEM.PERM_LOCATION) " .
                "as SORT_SEQ",
            "ITEM.ITEM_TYPE_ID",
            "ITEM.TEMP_ITEM_TYPE_ID",
        ];

        // From
        $sqlFrom = [
            $this->dbName . ".BIB_MASTER",
            $this->dbName . ".BIB_MFHD",
            $this->dbName . ".ITEM",
            $this->dbName . ".ITEM_STATUS_TYPE",
            $this->dbName . ".ITEM_STATUS",
            $this->dbName . ".LOCATION",
            $this->dbName . ".MFHD_ITEM",
            $this->dbName . ".MFHD_MASTER",
            $this->dbName . ".MFHD_DATA",
            $this->dbName . ".CIRC_TRANSACTIONS",
            $this->dbName . ".ITEM_BARCODE",
        ];

        // Where
        $sqlWhere = [
            "BIB_MASTER.BIB_ID = :id",
            "BIB_MASTER.BIB_ID = BIB_MFHD.BIB_ID",
            "BIB_MFHD.MFHD_ID = MFHD_MASTER.MFHD_ID",
            "MFHD_MASTER.MFHD_ID = MFHD_ITEM.MFHD_ID ",
            "MFHD_ITEM.ITEM_ID = ITEM.ITEM_ID ",
            "ITEM.ITEM_ID = ITEM_BARCODE.ITEM_ID(+)",
            "MFHD_DATA.MFHD_ID = MFHD_ITEM.MFHD_ID ",
            "ITEM.ITEM_ID = ITEM_STATUS.ITEM_ID ",
            "LOCATION.LOCATION_ID = ITEM.PERM_LOCATION ",
            "ITEM_STATUS.ITEM_STATUS = ITEM_STATUS_TYPE.ITEM_STATUS_TYPE",
            "ITEM.ITEM_ID = CIRC_TRANSACTIONS.ITEM_ID(+)",
            "BIB_MASTER.SUPPRESS_IN_OPAC='N'",
            "MFHD_MASTER.SUPPRESS_IN_OPAC='N' ",
            "(ITEM_BARCODE.BARCODE_STATUS is null OR ITEM_BARCODE.BARCODE_STATUS = 1)",
        ];

        // Order
        $sqlOrder = ["MFHD_DATA.MFHD_ID", "MFHD_DATA.SEQNUM"];

        // Bind
        $sqlBind = [':id' => $id];

        $sqlArray = [
            'expressions' => $sqlExpressions,
            'from' => $sqlFrom,
            'where' => $sqlWhere,
            'order' => $sqlOrder,
            'bind' => $sqlBind,
        ];

        return $sqlArray;
    }

    // overriden because we want to be able to treat an AAA scenario
    // as if it were a callslip (StorageRetrievalRequest).
    // NOTE: We purposely overrode MultiBackend::getILLPickupLocations()
    //       to pass the source in the ID because we need this info!
    public function getILLPickupLocations($id, $pickupLib, $patron)
    {
        // Parse out the source library, e.g., UIUdb.123 => 123
        // (We purposely overrode MultiBackend::getILLPickupLocations()
        //  to pass the source in the ID because we need this info!)
        list($source, $id) = explode('.', $id, 2);

        $isDone = false;
        if (preg_match('/^[1@]*(.+)[Dd][Bb]/', $pickupLib, $matches)) {
            //$item_agency_id_lc3 = strtolower($matches[1]);
            //$item_agency_id = strtoupper($item_agency_id_lc3) . 'db';
            $item_agency_id = $this->ubCodeToLibCode($pickupLib);
            list($patronUbId, $patronId) = explode('.', $patron['id'], 2);

            // It's an AAA scenario! (callslip)
            if ($source == $item_agency_id && $item_agency_id == $patronUbId) {
                $results = parent::getPickUpLocations($patron, NULL);
                $isDone = true;
                $ILLresults = array();
                foreach ($results as $result) {
                    $ILLresult = array();
                    $ILLresult['id'] = $result['locationID'];
                    $ILLresult['name'] = $result['locationDisplay'];
                    $ILLresults[] = $ILLresult;
                }
                $results = &$ILLresults;
            }
        }

        if(! $isDone) {
            try {
                $results =  parent::getILLPickupLocations($id, $pickupLib, $patron);
            } catch (ILSException $e) {
                $results = array();
                $result = array();
                $result['id'] = '-1';
                $result['name'] = $this->translate($e->getMessage());
                return $results;
                //throw new ILSException($e->getMessage());
            }
        }

        // look up system-wide default
        $dpul = $this->getDefaultPickUpLocation($patron);
        foreach ($results as &$result) {
            $result['isDefault'] = ($dpul === $result['id']);
        }

        return $results;
    }

    // We override this method because we need to force a
    // placeStorageRetrievalRequest() for AAA scenarios.
    // I.e., when Patron's Home Library, Item's Library, and Pickup Library are the same
    public function placeILLRequest($details)
    {
        $patron = $details['patron'];
        list($source, $patronId) = explode('.', $patron['id'], 2);
        $patronHomeUbId = $this->config['ILLRequestSources'][$source];
        $pickupLibrary = $details['pickUpLibrary'];
        $localUbId = $this->ws_patronHomeUbId;

        // It's an AAA scenario; we need to use Callslip here!
        if ($patronHomeUbId == $localUbId && $localUbId == $pickupLibrary) {

            // some param massaging necessary:
            $details['patron']['id'] = $patronId;
            // placeILLRequest uses slightly different param name for pickUpLocation!
            $details['pickUpLocation'] = $details['pickUpLibraryLocation'];

            return $this->placeStorageRetrievalRequest($details);
        }

        return parent::placeILLRequest($details);
    }

    public function getCourses()
    {
        $courseList = [];

        $sql = "select COURSE.COURSE_NUMBER || ': ' || COURSE.COURSE_NAME as NAME," .
               " COURSE.COURSE_ID " .
               "from $this->dbName.RESERVE_LIST, " .
               "$this->dbName.RESERVE_LIST_COURSES, $this->dbName.COURSE " .
               "where RESERVE_LIST.RESERVE_LIST_ID = " .
               "RESERVE_LIST_COURSES.RESERVE_LIST_ID and " .
               "RESERVE_LIST_COURSES.COURSE_ID = COURSE.COURSE_ID " . 
               " and " .
               "RESERVE_LIST.EFFECT_DATE<SYSDATE and " .
               "RESERVE_LIST.EXPIRE_DATE>SYSDATE " .
               "group by COURSE.COURSE_ID, COURSE_NUMBER, COURSE_NAME " .
               "order by COURSE_NUMBER";
        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $courseList[$row['COURSE_ID']] = $row['NAME'];
            }
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        return $courseList;
    }

    public function getInstructors()
    {
        $instList = [];

        $sql = "select INSTRUCTOR.INSTRUCTOR_ID, " .
               "INSTRUCTOR.LAST_NAME || ', ' || INSTRUCTOR.FIRST_NAME as NAME " .
               "from $this->dbName.RESERVE_LIST, " .
               "$this->dbName.RESERVE_LIST_COURSES, $this->dbName.INSTRUCTOR " .
               "where RESERVE_LIST.RESERVE_LIST_ID = " .
               "RESERVE_LIST_COURSES.RESERVE_LIST_ID and " .
               "RESERVE_LIST_COURSES.INSTRUCTOR_ID = INSTRUCTOR.INSTRUCTOR_ID " .
               " and " .
               "RESERVE_LIST.EFFECT_DATE<SYSDATE and " .
               "RESERVE_LIST.EXPIRE_DATE>SYSDATE " .
               "group by INSTRUCTOR.INSTRUCTOR_ID, LAST_NAME, FIRST_NAME " .
               "order by LAST_NAME";
        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $instList[$row['INSTRUCTOR_ID']] = $row['NAME'];
            }
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        return $instList;
    }

    public function getDepartments()
    {
        $deptList = [];

        $sql = "select DEPARTMENT.DEPARTMENT_ID, DEPARTMENT.DEPARTMENT_NAME " .
               "from $this->dbName.RESERVE_LIST, " .
               "$this->dbName.RESERVE_LIST_COURSES, $this->dbName.DEPARTMENT " .
               "where " .
               "RESERVE_LIST.RESERVE_LIST_ID = " .
               "RESERVE_LIST_COURSES.RESERVE_LIST_ID and " .
               "RESERVE_LIST_COURSES.DEPARTMENT_ID = DEPARTMENT.DEPARTMENT_ID " .
               " and " .
               "RESERVE_LIST.EFFECT_DATE<SYSDATE and " .
               "RESERVE_LIST.EXPIRE_DATE>SYSDATE " .
               "group by DEPARTMENT.DEPARTMENT_ID, DEPARTMENT_NAME " .
               "order by DEPARTMENT_NAME";
        try {
            $sqlStmt = $this->executeSQL($sql);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $deptList[$row['DEPARTMENT_ID']] = $row['DEPARTMENT_NAME'];
            }
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        return $deptList;
    }

    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        if ($this->defaultPickUpLocation) {
            return $this->defaultPickUpLocation;
        }

        if ($patron) {
            $patronId = $this->encodeXML($patron['id']);
            $lastname = $this->encodeXML($patron['lastname']);
            $barcode = $this->encodeXML($patron['cat_username']);
            $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

            $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:parameters>
      <ser:parameter key="pickupLibId">
          <ser:value>$localUbId</ser:value>
        </ser:parameter>
  </ser:parameters>
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId"
    patronId="$patronId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;

            $response = $this->makeRequest(
                ['UBPickupLibService' => false], [], 'POST', $xml
            );
            if ($response === false) {
                throw new ILSException('mytransactions_error');
            }

            // Process
            $myreq_ns = 'http://www.endinfosys.com/Voyager/requests';
            $response->registerXPathNamespace(
                'ser', 'http://www.endinfosys.com/Voyager/serviceParameters'
            );
            $response->registerXPathNamespace('req', $myreq_ns);

            foreach ($response->xpath('//req:pickUpLocations') as $pickUpLoc) {
                $pickUpLoc = $pickUpLoc->children($myreq_ns);

                foreach ($pickUpLoc->location as $location) {
                    $isDefault = (string) $location->attributes()->isDefault;
                    $pickUpLocId = (string) $location->attributes()->id;
                    if ($isDefault == "Y") {
                        return $pickUpLocId;
                    }
                }
            }
        }

        return $this->defaultPickUpLocation;
    }

    // This is an override because the RESTful method circulationActions is inconsistent 
    // The XMLoverHTTP method, MyAccountService, seems more stable
    public function getMyTransactions2($patron)
    {
            $patronId = $this->encodeXML($patron['id']);
            $lastname = $this->encodeXML($patron['lastname']);
            $barcode = $this->encodeXML($patron['cat_username']);
            $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

            $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:parameters/>
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId"
    patronId="$patronId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;

            $response = $this->makeRequest(
                //['MyAccountService' => false], ['patronId' => $patronId, 'patronHomeUbId' => $localUbId], 'POST', $xml
                ['MyAccountService' => false], [], 'POST', $xml
            );
            if ($response === false) {
                throw new ILSException('mytransactions_error');
            }

            // Process
            $myac_ns = 'http://www.endinfosys.com/Voyager/myAccount';
            $response->registerXPathNamespace(
                'ser', 'http://www.endinfosys.com/Voyager/serviceParameters'
            );
            $response->registerXPathNamespace('myac', $myac_ns);
/*
            // The service doesn't actually return messages (in Voyager 8.1),
            // but maybe in the future...
            foreach ($response->xpath('//ser:message') as $message) {
                if ($message->attributes()->type == 'system'
                    || $message->attributes()->type == 'error'
                ) {
                    return false;
                }
            }
*/

            $finalResult = [];
            foreach ($response->xpath('//myac:clusterChargedItems') as $cluster) {
                $cluster = $cluster->children($myac_ns);
                $dbKey = (string)$cluster->cluster->ubSiteId;
                foreach ($cluster->chargedItem as $chargedItem) {
                    $chargedItem = $chargedItem->children($myac_ns);

                    $result = [];
                    $result['institution_name'] = (string)$cluster->cluster->clusterName;
                    $result['institution_id'] = $dbKey;
                    $result['institution_dbkey'] = $dbKey;
                    $result['item_id'] = (string)$chargedItem->itemId;
                    $result['id'] = $result['institution_id'] . '_' . $result['item_id'];
                    $result['dueStatus'] = $this->getChargedStatusCode((string)$chargedItem->statusCode);
                    $result['dueStatusAlert'] = $this->getChargedStatusCodeAlert((string)$chargedItem->statusCode);
                    $result['title'] = (string)$chargedItem->title;
                    $result['author'] = (string)$chargedItem->author;
                    $result['location'] = (string)$chargedItem->location;
                    $result['renewable'] = (string)$chargedItem->renewable;

                    $result['enumeration'] = (string)$chargedItem->enumeration;
                    $result['callNumber'] = (string)$chargedItem->callNumber;
                    $result['itemBarcode'] = (string)$chargedItem->itemBarcode;

                    // Let's sort by due date!!!
                    // 2018-03-27T23:59:00.000-05:00
                    // 2018-03-28T23:59:00.000-05:00
                    // ...
                    $result['duedate_sort'] = (string)$chargedItem->dueDate;
                    if (array_key_exists('sortBy', $_POST) && $_POST['sortBy'] == 'title') {
                        $result['sort_by'] = $result['title'];
                    } else if (array_key_exists('sortBy', $_POST) &&$_POST['sortBy'] == 'library') {
                        $result['sort_by'] = $result['institution_name'] . $result['duedate_sort'] . $result['title'];
                    } else {
                        // duedate - default
                        $result['sort_by'] = $result['duedate_sort'] . $result['institution_name'] . $result['title'];
                    }

                    $dueDate = (string)$chargedItem->dueDate;
                    try {
                        $newDate = date_format(date_create($dueDate), 'F d, Y');
                        $response['new_date'] = $newDate;
                    } catch (DateException $e) {
                        // If we can't parse out the date, use the raw string:
                        $response['new_date'] = $dueDate;
                    }
                    try {
                        $newTime = date_format(date_create($dueDate), 'g:i A');
                        $response['new_time'] = $newTime;
                    } catch (DateException $e) {
                        // If we can't parse out the time, just ignore it:
                        $response['new_time'] = false;
                    }
                    $result['duedate'] = $newDate;
                    $result['dueTime'] = $newTime;

                    $finalResult[] = $result;
                }
            }
        usort($finalResult, function($a, $b) { return strcmp($a{'sort_by'}, $b{'sort_by'}); });
        return $finalResult;
    }

    protected function determineAvailability($statusArray)
    {
        // It's possible for a record to have multiple status codes.  We
        // need to loop through in search of the "Not Charged" (i.e. on
        // shelf) status, collecting any other statuses we find along the
        // way...
        $notCharged = false;
        $otherStatuses = [];
        foreach ($statusArray as $status) {
            switch ($status) {
            // Treat the following statuses as if they were 'Not Charged':
            case 'Discharged':
            case 'Cataloging Review':
            case 'Circulation Review':
            // The real 'Not Charged' status:
            case 'Not Charged':
                $notCharged = true;
                break;
            default:
                $otherStatuses[] = $status;
                break;
            }
        }

        // If we found other statuses or if we failed to find "Not Charged,"
        // the item is not available!
        $available = (count($otherStatuses) == 0 && $notCharged);

        return ['available' => $available, 'otherStatuses' => $otherStatuses];
    }


    protected function checkAccountBlocks($patronId)
    {
        $callingFunction = debug_backtrace()[1]['function'];
        // We do NOT want to prevent Request/Renew capability; let the VXWS API sort this out.
        // This is because we do NOT want a block from one institution preventing requests/renews from other institutions
        // But we DO want to show block reasons on Checked Out Items (getAccountBlocks) page...
        if ($callingFunction != "getAccountBlocks") {
            return false;
        }
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\nCALLING FUNCTION:" . var_export($callingFunction, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        $cacheId = "blocks|$patronId";
        $blockReasons = $this->getCachedData($cacheId);
        if (null === $blockReasons) {
            // Build Hierarchy
            $hierarchy = [
                'patron' =>  $patronId,
                'patronStatus' => 'blocks'
            ];

            // Add Required Params
            $params = [
                'patron_homedb' => $this->ws_patronHomeUbId,
                'view' => 'full'
            ];

            $blocks = $this->makeRequest($hierarchy, $params);
            if ($blocks
                && (string)$blocks->{'reply-text'} == 'ok'
                && isset($blocks->blocks->institution->borrowingBlock)
            ) {
                $blockReasons = $this->extractBlockReasons(
                    // CARLI edit: send all institutions' blocks, not just the first one!
                    //$blocks->blocks->institution->borrowingBlock
                    $blocks->blocks
                );
            } else {
                $blockReasons = [];
            }
            $this->putCachedData($cacheId, $blockReasons);
        }
        return $blockReasons;
    }

    // CARLI edit: parse each inst blocks, instead of assuming just one inst
    protected function extractBlockReasons($borrowBlocks)
    {
        $whitelistConfig = isset($this->config['Patron']['ignoredBlockCodes'])
            ? $this->config['Patron']['ignoredBlockCodes'] : '';
        $whitelist = array_map('trim', explode(',', $whitelistConfig));

        $blockReason = [];
        // CARLI added foreach inst:
        foreach ($borrowBlocks->institution as $inst) {
            $instName = $inst->instName;
            // CARLI edit:
            //foreach ($borrowBlocks as $borrowBlock) {
            foreach ($inst->borrowingBlock as $borrowBlock) {
                if (!in_array((string)$borrowBlock->blockCode, $whitelist)) {
                    $blockReason[] = $instName . ': ' . (string)$borrowBlock->blockReason;
                }
            }
        }
        return $blockReason;
    }


    /**
     * UB Map
     *
     * @var array
     */
    protected $ubMap_libraryToKey = [];
    protected $ubMap_keyToLibrary = [];

    public function init()
    {
        parent::init();

        $this->loadUbMap('ubMap.ini');
    }

    /**
     * Loads UB information from configuration file.
     *
     * @param string $filename File to load from
     *
     * @throws ILSException
     * @return void
     */
    protected function loadUbMap($filename)
    {
        // Load ubMap file:
        $ubFile
            = ConfigLocator::getConfigPath($filename, 'config/vufind');
        if (!file_exists($ubFile)) {
            throw new ILSException(
                "Cannot load ub file: {$ubFile}."
            );
        }
        if (($handle = fopen($ubFile, "r")) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $this->ubMap_libraryToKey[$data[0]] = $data[1];
                $this->ubMap_keyToLibrary[$data[1]] = $data[0];
            }
            fclose($handle);
        }
    }

    protected function mapKeyToLibrary($key)
    {
       if (array_key_exists($key, $this->ubMap_keyToLibrary)) {
           return $this->ubMap_keyToLibrary[$key];
       }
       return null;
    }

    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
    /**
     * Renew My Items
     *
     * Function for attempting to renew a patron's items.  The data in
     * $renewDetails['details'] is determined by getRenewDetails().
     *
     * @param array $renewDetails An array of data required for renewing items
     * including the Patron ID and an array of renewal IDS
     *
     * @return array              An array of renewal information keyed by item ID
     */
     // CARLI EDIT: The original function always uses the patron's web service. But this doesn't
     // seem to work in our UB environment. Instead, we need to call the item's library's RenewService 
     // for each item. For now, we will simply split each item up into individual calls; later, we might
     // want to consider grouping by item library and making a batch call per library.
    public function renewMyItems($renewDetails)
    {
//$debug = 'In CARLI::renewMyItems, renewDetails: ' . var_export($renewDetails, true);
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);
        $patron = $renewDetails['patron'];
        $finalResult = ['details' => []];

        // Get Account Blocks
        $finalResult['blocks'] = $this->checkAccountBlocks($patron['id']);

        if (!$finalResult['blocks']) {
            // Add Items and Attempt Renewal
            $itemIdentifiers = '';

          $wsAppOriginal = $this->ws_app; // probably not necessary, but just in case
          foreach ($renewDetails['details'] as $renewID) {
                list($dbKey, $loanId) = explode('|', $renewID);
//$debug = 'In CARLI::renewMyItems, dbKey: ' . $dbKey . ' ; loanId: ' . $loanId;
//$debug .= "\n" . '$this->ws_dbKey: ' . $this->ws_dbKey . ' $this->ws_app: ' . $this->ws_app;
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);
                if (!$dbKey) {
                    $dbKey = $this->ws_dbKey;
                } else {
                    // CARLI EDIT: set ws_app to item library's
                    $itemLibrary = $this->mapKeyToLibrary($dbKey);
                    if ($itemLibrary) {
                       $this->ws_app = $itemLibrary . '/vxws';
                    }
                }

                $loanId = $this->encodeXML($loanId);
                $dbKey = $this->encodeXML($dbKey);

                $itemIdentifiers = ''; // CARLI EDIT: we are calling the webservice each time per item
                $itemIdentifiers .= <<<EOT
      <myac:itemIdentifier>
       <myac:itemId>$loanId</myac:itemId>
       <myac:ubId>$dbKey</myac:ubId>
      </myac:itemIdentifier>
EOT;

            $patronId = $this->encodeXML($patron['id']);
            $lastname = $this->encodeXML($patron['lastname']);
            $barcode = $this->encodeXML($patron['cat_username']);
            $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

            // The RenewService has a weird prerequisite that
            // AuthenticatePatronService must be called first and JSESSIONID header
            // be preserved. There's no explanation why this is required, and a
            // quick check implies that RenewService works without it at least in
            // Voyager 8.1, but who knows if it fails with UB or something, so let's
            // try to play along with the rules.
            $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;

            $response = $this->makeRequest(
                ['AuthenticatePatronService' => false], [], 'POST', $xml
            );
            if ($response === false) {
                throw new ILSException('renew_error');
            }

            $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
   <ser:parameters/>
   <ser:definedParameters xsi:type="myac:myAccountServiceParametersType"
   xmlns:myac="http://www.endinfosys.com/Voyager/myAccount"
   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
$itemIdentifiers
   </ser:definedParameters>
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId"
  patronId="$patronId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;

            $response = $this->makeRequest(
                ['RenewService' => false], [], 'POST', $xml
            );
            if ($response === false) {
                throw new ILSException('renew_error');
            }

            // Process
            $myac_ns = 'http://www.endinfosys.com/Voyager/myAccount';
            $response->registerXPathNamespace(
                'ser', 'http://www.endinfosys.com/Voyager/serviceParameters'
            );
            $response->registerXPathNamespace('myac', $myac_ns);
            // The service doesn't actually return messages (in Voyager 8.1),
            // but maybe in the future...
            foreach ($response->xpath('//ser:message') as $message) {
                if ($message->attributes()->type == 'system'
                    || $message->attributes()->type == 'error'
                ) {
                    return false;
                }
            }
            foreach ($response->xpath('//myac:clusterChargedItems') as $cluster) {
                $cluster = $cluster->children($myac_ns);
                $dbKey = (string)$cluster->cluster->ubSiteId;
                foreach ($cluster->chargedItem as $chargedItem) {
                    $chargedItem = $chargedItem->children($myac_ns);
                    $renewStatus = $chargedItem->renewStatus;
                    if (!$renewStatus) {
                        continue;
                    }
                    $renewed = false;
                    foreach ($renewStatus->status as $status) {
                        if ((string)$status == 'Renewed') {
                            $renewed = true;
                        }
                    }

                    $result = [];
                    $result['item_id'] = (string)$chargedItem->itemId;
                    $result['sysMessage'] = (string)$renewStatus->status;

                    $dueDate = (string)$chargedItem->dueDate;
                    try {
                        $newDate = date_format(date_create($dueDate), 'F d, Y');
                        $response['new_date'] = $newDate;
                    } catch (DateException $e) {
                        // If we can't parse out the date, use the raw string:
                        $response['new_date'] = $dueDate;
                    }
                    try {
                        $newTime = date_format(date_create($dueDate), 'g:i A');
                        $response['new_time'] = $newTime;
                    } catch (DateException $e) {
                        // If we can't parse out the time, just ignore it:
                        $response['new_time'] = false;
                    }
                    $result['new_date'] = $newDate;
                    $result['new_time'] = $newTime;
                    $result['success'] = $renewed;

                    $finalResult['details'][$result['item_id']] = $result;
                }
            }
          } // for each item
          $this->ws_app = $wsAppOriginal; // probably not necessary, but just in case
        }
        return $finalResult;
    }


    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return mixed        Array of the patron's fines on success.
     */
    public function getMyFines($patron)
    {
        $localUbId = $this->encodeXML($this->ws_patronHomeUbId);
        $lastname = $patron['cat_password'];
        $barcode = $patron['cat_username'];
        $patronId = $patron['id'];

        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId" patronId="$patronId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;


//file_put_contents("/usr/local/vufind/look.txt", "CARLI::VoyagerRestful::getMyFines() patron = " . var_export($patron, true) . "\n\nubid = " . $localUbId .  "\n\n", FILE_APPEND | LOCK_EX);

        $response = $this->makeRequest(
            ['MyAccountService' => false], [], 'POST', $xml
        );
        if ($response === false) {
            return null;
        }
//file_put_contents("/usr/local/vufind/look.txt", "CARLI::VoyagerRestful::getMyFines() response = " . var_export($response, true) . "\n\n", FILE_APPEND | LOCK_EX);

        // Process
        $myac_ns = 'http://www.endinfosys.com/Voyager/myAccount';
        $response->registerXPathNamespace(
            'ser', 'http://www.endinfosys.com/Voyager/serviceParameters'
        );
        $response->registerXPathNamespace('myac', $myac_ns);

        $fineList = [];

        foreach ($response->xpath('//myac:clusterFinesFees') as $cluster) {
            $cluster = $cluster->children($myac_ns);
            $dbKey = (string)$cluster->cluster->ubSiteId;
            $instName = (string)$cluster->cluster->clusterName;
            foreach ($cluster->fineFee as $fineFee) {
                $fineFee = $fineFee->children($myac_ns);

                $result = [];
                $result['id'] = (string)$fineFee->fineFeeId;
                $result['institution_id'] = '';
                $result['institution_name'] = $instName;
                $result['institution_dbkey'] = $dbKey;

                $date = '';
                try {
                    $date = $this->dateFormat->convertToDisplayDate(
                        'Y-m-d H:i', (string)$fineFee->date
                    );
                } catch (DateException $e) {
                    // If we can't parse out the date, use the raw string:
                    $date = (string)$fineFee->date;
                }
                $result['date'] = $date;

                $result['title'] = (string)$fineFee->title;
                $result['title2'] = (string)$fineFee->title;
                $result['type'] = (string)$fineFee->postingType;
                $result['amount'] = (string)$fineFee->amount->amount;
                $result['amount_total'] = (string)$fineFee->amountTotal->amount;
                $result['amount_balance'] = (string)$fineFee->balance->amount;
                $result['amount_balance_total'] = (string)$fineFee->balanceTotal->amount;
                $fineList[] = $result;
            }
        }

        return $fineList;
    }


    public function patronLogin($barcode, $lastname) 
    {
//$debug = 'In patronLogin... barcode: ' . $barcode . ' lastname: ' . $lastname;
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;

        // makeRequest2 includes a workaround libxml issue with Voyager API data
        $response = $this->makeRequest2(
            ['AuthenticatePatronService' => false], [], 'POST', $xml
        );
        if ($response === false) {
            return null;
        }

        // We must enforce local patrons only! I.e., do not allow someone from aff2 to authenticate for aff1
        if (!$response->serviceData || !$response->serviceData->isLocalPatron || $response->serviceData->isLocalPatron != "Y") {
            return null;
        }


        // There's got to be a better way. But I only need the one attribute for now.
        $patronIdentifier = $response->serviceData->patronIdentifier;
        $atts_object = $patronIdentifier->attributes();
        $atts_array = (array) $atts_object;
        $atts_array = $atts_array['@attributes'];
        $patronId = $atts_array['patronId'];

        //$firstName = $response->serviceData->fullName; // better info comes from PersonalInfoService
        //$lastName = $response->serviceData->lastName; // better info comes from PersonalInfoService

        // Acquire email address, firstname, lastname
        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<ser:serviceParameters
xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">
  <ser:parameters>
  </ser:parameters>
  <ser:patronIdentifier lastName="$lastname" patronHomeUbId="$localUbId"
  patronId="$patronId">
    <ser:authFactor type="B">$barcode</ser:authFactor>
  </ser:patronIdentifier>
</ser:serviceParameters>
EOT;
        $response = $this->makeRequest2(
            ['PersonalInfoService' => false], [], 'POST', $xml
        );
        $emailAddress = $response->serviceData->emailAddress->address;
        $firstName = $response->serviceData->name->firstName;
        $lastName = $response->serviceData->name->lastName;

        return [
            'id' => utf8_encode($patronId),
            'firstname' => utf8_encode($firstName),
            'lastname' => utf8_encode($lastName),
            'cat_username' => utf8_encode($barcode),
            'cat_password' => utf8_encode($lastname),
            'email' => utf8_encode($emailAddress),
            'major' => null,
            'college' => null];

       //return parent::patronLogin($barcode, $lastname);
    }

    public function getNewItems($page, $limit, $daysOld, $fundId = null)
    {
        $oracleInstance = getenv('VUFIND_LIBRARY_DB');

        $items = [];

        $bindParams = [
            #':enddate' => date('d-m-Y', strtotime('now')),
            ':enddate' => date('d-m-Y H:i:s', strtotime('yesterday 22:00:00')),
            #':startdate' => date('d-m-Y', strtotime('-' . $daysOld . ' day'))
            ':startdate' => date('d-m-Y', strtotime('-' . ($daysOld + 1) . ' day'))
# hardcoded for now because devel server doesn't have any recent data!
#':startdate' => '01-03-2016',
#':enddate' => '01-01-2017'
        ];

        $sql = 
"select count(distinct bib_id) as count from      "  .
"(select distinct ${oracleInstance}.bib_master.bib_id, ${oracleInstance}.item.create_date as cdate      "  .
"from      ${oracleInstance}.bib_master,     "  .
"         ${oracleInstance}.bib_text,     "  .
"         ${oracleInstance}.bib_mfhd,     "  .
"         ${oracleInstance}.mfhd_item,     "  .
"         ${oracleInstance}.mfhd_master,     "  .
"         ${oracleInstance}.item     "  .
"where     ${oracleInstance}.bib_master.bib_id=${oracleInstance}.bib_text.bib_id and     "  .
"         ${oracleInstance}.bib_text.bib_id=${oracleInstance}.bib_mfhd.bib_id and     "  .
"         ${oracleInstance}.bib_mfhd.mfhd_id=${oracleInstance}.mfhd_master.mfhd_id and     "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=${oracleInstance}.mfhd_item.mfhd_id and     "  .
"         ${oracleInstance}.mfhd_item.item_id=${oracleInstance}.item.item_id and     "  .
"         ${oracleInstance}.mfhd_master.suppress_in_opac not in ('Y') and     "  .
"         ${oracleInstance}.bib_master.suppress_in_opac not in ('Y') and     "  .
"         ${oracleInstance}.item.on_reserve not in ('Y') and     "  .
"         substr(${oracleInstance}.bib_text.bib_format,-1,1) in ('a','c','m') and     "  .
"         ${oracleInstance}.item.create_date between to_date(:startdate, 'dd-mm-yyyy') and     "  .
#"         to_date(:enddate, 'dd-mm-yyyy') and     "  .
"         to_date(:enddate, 'dd-mm-yyyy HH24:MI:SS') and     "  .
"         ((${oracleInstance}.mfhd_master.create_date between     "  .
"            to_date(:startdate, 'dd-mm-yyyy') and     "  .
#"            to_date(:enddate, 'dd-mm-yyyy'))  or     "  .
"            to_date(:enddate, 'dd-mm-yyyy HH24:MI:SS'))  or     "  .
"          (${oracleInstance}.mfhd_master.update_date between     "  .
"            to_date(:startdate, 'dd-mm-yyyy') and     "  .
#"            to_date(:enddate, 'dd-mm-yyyy')))     "  .
"            to_date(:enddate, 'dd-mm-yyyy HH24:MI:SS')))     "  .
"UNION     "  .
"select distinct ${oracleInstance}.bib_master.bib_id, ${oracleInstance}.mfhd_master.create_date as cdate     "  .
"from      ${oracleInstance}.bib_master,     "  .
"         ${oracleInstance}.bib_mfhd,     "  .
"         ${oracleInstance}.mfhd_item,     "  .
"         ${oracleInstance}.mfhd_master,     "  .
"         (select record_id, link     "  .
"          from ${oracleInstance}.elink_index     "  .
"          where record_type='M') elink     "  .
"where     ${oracleInstance}.bib_master.bib_id=${oracleInstance}.bib_mfhd.bib_id and     "  .
"         ${oracleInstance}.bib_mfhd.mfhd_id=${oracleInstance}.mfhd_master.mfhd_id and     "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=elink.record_id and     "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=${oracleInstance}.mfhd_item.mfhd_id(+) and     "  .
"         ${oracleInstance}.mfhd_item.item_id is null and     "  .
"         ${oracleInstance}.mfhd_master.suppress_in_opac not in ('Y') and     "  .
"         ${oracleInstance}.bib_master.suppress_in_opac not in ('Y') and     "  .
"         ${oracleInstance}.mfhd_master.create_date between to_date(:startdate, 'dd-mm-yyyy') and     "  .
#"         to_date(:enddate, 'dd-mm-yyyy') and     "  .
"         to_date(:enddate, 'dd-mm-yyyy HH24:MI:SS') and     "  .
"         elink.link is not null)     "  
;

        try {
            $sqlStmt = $this->executeSQL($sql, $bindParams);
            $row = $sqlStmt->fetch(PDO::FETCH_ASSOC);
            $items['count'] = $row['COUNT'];
//file_put_contents("/usr/local/vufind/look.txt", "new items count:\n" . var_export($items['count'], true) . "\n\nbindParmams:\n" . var_export($bindParams, true) . "\n\nsql:\n" . var_export($sql, true) . "\n\n", FILE_APPEND | LOCK_EX);
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }

        $page = ($page) ? $page : 1;
        $limit = ($limit) ? $limit : 20;
        $bindParams[':startRow'] = (($page - 1) * $limit) + 1;
        $bindParams[':endRow'] = ($page * $limit);
        $sql = 
"select * from                      "  .
"(select a.*, rownum rnum from                      "  .
"(select bib_id from                      "  .
"(select distinct ${oracleInstance}.bib_master.bib_id, ${oracleInstance}.item.create_date as cdate                      "  .
"from      ${oracleInstance}.bib_master,                      "  .
"         ${oracleInstance}.bib_text,                      "  .
"         ${oracleInstance}.bib_mfhd,                      "  .
"         ${oracleInstance}.mfhd_item,                      "  .
"         ${oracleInstance}.mfhd_master,                      "  .
"         ${oracleInstance}.item                      "  .
"where     ${oracleInstance}.bib_master.bib_id=${oracleInstance}.bib_text.bib_id and                      "  .
"         ${oracleInstance}.bib_text.bib_id=${oracleInstance}.bib_mfhd.bib_id and                      "  .
"         ${oracleInstance}.bib_mfhd.mfhd_id=${oracleInstance}.mfhd_master.mfhd_id and                      "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=${oracleInstance}.mfhd_item.mfhd_id and                      "  .
"         ${oracleInstance}.mfhd_item.item_id=${oracleInstance}.item.item_id and                      "  .
"         ${oracleInstance}.mfhd_master.suppress_in_opac not in ('Y') and                      "  .
"         ${oracleInstance}.bib_master.suppress_in_opac not in ('Y') and                      "  .
"         ${oracleInstance}.item.on_reserve not in ('Y') and                      "  .
"         substr(${oracleInstance}.bib_text.bib_format,-1,1) in ('a','c','m') and                      "  .
"        ${oracleInstance}.item.create_date between to_date(:startdate, 'dd-mm-yyyy') and                      "  .
#"         to_date(:enddate, 'dd-mm-yyyy') and                      "  .
"         to_date(:enddate, 'dd-mm-yyyy HH24:MI:SS') and                      "  .
"         ((${oracleInstance}.mfhd_master.create_date between                      "  .
"            to_date(:startdate, 'dd-mm-yyyy') and                      "  .
#"            to_date(:enddate, 'dd-mm-yyyy'))  or                      "  .
"            to_date(:enddate, 'dd-mm-yyyy HH24:MI:SS'))  or                      "  .
"          (${oracleInstance}.mfhd_master.update_date between                      "  .
"            to_date(:startdate, 'dd-mm-yyyy') and                      "  .
#"            to_date(:enddate, 'dd-mm-yyyy')))                      "  .
"            to_date(:enddate, 'dd-mm-yyyy HH24:MI:SS')))                      "  .
"UNION                      "  .
"select distinct ${oracleInstance}.bib_master.bib_id, ${oracleInstance}.mfhd_master.create_date as cdate                      "  .
"from      ${oracleInstance}.bib_master,                      "  .
"         ${oracleInstance}.bib_mfhd,                      "  .
"         ${oracleInstance}.mfhd_item,                      "  .
"         ${oracleInstance}.mfhd_master,                      "  .
"         (select record_id, link                      "  .
"          from ${oracleInstance}.elink_index                      "  .
"          where record_type='M') elink                      "  .
"where     ${oracleInstance}.bib_master.bib_id=${oracleInstance}.bib_mfhd.bib_id and                      "  .
"         ${oracleInstance}.bib_mfhd.mfhd_id=${oracleInstance}.mfhd_master.mfhd_id and                      "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=elink.record_id and                      "  .
"         ${oracleInstance}.mfhd_master.mfhd_id=${oracleInstance}.mfhd_item.mfhd_id(+) and                      "  .
"         ${oracleInstance}.mfhd_item.item_id is null and                      "  .
"         ${oracleInstance}.mfhd_master.suppress_in_opac not in ('Y') and                      "  .
"         ${oracleInstance}.bib_master.suppress_in_opac not in ('Y') and                      "  .
"         ${oracleInstance}.mfhd_master.create_date between to_date(:startdate, 'dd-mm-yyyy') and                      "  .
#"         to_date(:enddate, 'dd-mm-yyyy') and                      "  .
"         to_date(:enddate, 'dd-mm-yyyy HH24:MI:SS') and                      "  .
"         elink.link is not null)                      "  .
"group by bib_id                      "  .
"order by max(cdate) desc) a                      "  .
"where rownum <= :endRow)                      "  .
"where rnum >= :startRow                      "  
;

        try {
            $sqlStmt = $this->executeSQL($sql, $bindParams);
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $items['results'][]['id'] = $row['BIB_ID'];
            }
            return $items;
        } catch (PDOException $e) {
            throw new ILSException($e->getMessage());
        }
    }



     // We always want local "callslip" info from VXWS services too
     // Because we are relying on VXWS data from VoyagerRestful and *not*
     // the SQL data from Voyager driver.
     // One way to make this happen is to override this method and return false always.
     protected function isLocalInst($institution)
     {
         return false;
     }


    public function getMyTransactions($patron)
    {
        $cnt = 0;
        $filteredResults = array();

        //$results = parent::getMyTransactions($patron); // bypass. buggy RESTful API call.
        $results = $this->getMyTransactions2($patron); // call our new XMLoverHTTP method, instead!
/*
// NO NEED to do this anymore, since we are now using our own cusotmized getMyTransactions2 method!

        foreach ($results as $result) {

            // If it has a renewLimit property, then we know it's a local version of getMyTransactions; skip these.
            // We only want to display the VXWS data.
            if (array_key_exists('renewLimit', $result) &&  $result['renewLimit']) continue;

            $filteredResults[] = $result;
        }
        return $filteredResults;
*/
        return $results; // see above: NO NEED...
    }

    public function getMyILLRequests($patron)
    {
        $ret = array_merge(
                       $this->getHoldsFromApi($patron, false),
                       $this->getCallSlips($patron, true) // local callslips too
        );

        $sort_priority_map = array (
           'R2' => '00',
           'H2' => '00',

           'H1' => '01',

           'C2' => '02',
           'C3' => '02',

           'C1' => '03',
           'C5' => '04',
           'R1' => '05',
           'H' => '06',
           'R6' => '07',
           'H6' => '08',
           'C6' => '09',
           'R5' => '10',
           'H5' => '11',
           'C7' => '12',
           'R3' => '13',
           'H3' => '14',
           'C8' => '15',
           'C9' => '16',
           'R4' => '17',
           'H4' => '18',
        );

        foreach ($ret as & $r) {
            if ($r['type'] == 'H') {
                if (preg_match('/^Position /', $r['status_text'] ) ||
                    preg_match('/^In transit to /', $r['status_text'] )) {
                    $r['status_text'] = 'Requested item in transit';
                }
            }
            elseif ($r['type'] == 'C') {
                if (preg_match('/^Expired /', $r['status_text'] )) {
                    $r['status_text'] = 'Request expired';
                } elseif (preg_match('/^Not Filled/', $r['status_text'] )) {
                    $r['status_text'] = 'Seeking next available';
                } elseif (preg_match('/^Printed/', $r['status_text'] )) {
                    $r['status_text'] = 'Request in process';
                } elseif (preg_match('/^Accepted/', $r['status_text'] )) {
                    $r['status_text'] = 'Request submitted';
                }

                if ($r['status'] == '2' || $r['status'] == '3') {
                    $r['status_text'] = 'Request in process';
                }
            }
            elseif ($r['type'] == 'R') {
                if (preg_match('/^Position [^:]*: /', $r['status_text'] )) {
                    $r['status_text'] = preg_replace('/^Position [^:]*: /', '', $r['status_text']);
                }
            }

            $sort_by_str = $r['type'] . $r['status'];
            $sort_by_val = array_key_exists($sort_by_str, $sort_priority_map) ? $sort_priority_map[$sort_by_str] : '99';
            $r['status_sort_data'] = $sort_by_val;
            $r['sort_by'] = $sort_by_val . $r['title'];
        }
        usort($ret, function($a, $b) { return strcmp($a{'sort_by'}, $b{'sort_by'}); });

        return $ret;
    }

    protected function getUBRequestDetails($id, $patron)
    {
        $results = parent::getUBRequestDetails($id, $patron);
        if (! $results) {
            return $results;
        }

        // Make certain that home library is included in list of Pickup libraries
        $libraries = $results['libraries'];
        $service = $this->translate(strtolower($this->config['Catalog']['service']));
        $localUbId = $this->encodeXML($this->ws_patronHomeUbId);

        $filteredLibraries = array();
        $filteredLibraries[] = [
            'id' => $localUbId,
            'name' => $service,
            'isDefault' => true
        ];

        foreach ($libraries as $library) {
            if (
                $library['id'] == $localUbId
                ||
                $library['id'] == '1@RESDB20020723111103'
            ) {
                continue;
            }
            $library['isDefault'] = false;
            $filteredLibraries[] = $library;
        }

        $results['libraries'] = $filteredLibraries;

        return $results;
    }

    protected function isStorageRetrievalRequestAllowed($holdingsRow)
    {
        // Disallow Callslip requesting. We use ILL requesting for everything now.
        return false;
    }

    protected function getHoldingData($sqlRows)
    {
        $rows = parent::getHoldingData($sqlRows);

        $deleteThese = array();
        foreach ($rows as $mfhd_id => & $this_row) {
            foreach ($this_row as $copy_number => & $row) {
                if (!isset($row['RECORD_SEGMENT'])) {
                    continue;
                }
                try {
                    $marc = new File_MARC(
                        str_replace(["\n", "\r"], '', $row['RECORD_SEGMENT']),
                        File_MARC::SOURCE_STRING
                    );
                    if ($record = $marc->next()) {
                        // GitHub Issue# 299: Use the 852$t for Copy Number instead!
                        if ($_852 = $record->getField('852')) {
                           if ($_852t = $_852->getSubfield('t')) {
                              $row['COPY_NUMBER'] = $_852t->getData();
                              // Determine Copy Number (append volume when available)
                              $number = '';
                              if (isset($row['COPY_NUMBER'])) {
                                  $number = $row['COPY_NUMBER'];
                              }
                              if (isset($row['ITEM_ENUM'])) {
                                  $number .= ' ' . utf8_encode($row['ITEM_ENUM']);
                              }
                              if (isset($row['CHRON'])) {
                                  $chron = $row['CHRON'];
                                  if (! preg_match('/\(/', $chron)) {
                                      $chron = '(' . $chron . ')';
                                  }
                                  $number .= ' ' . utf8_encode($chron);
                              }
                              // If "Copy Number" is different, we need to use it as the new key
                              // and delete the old one later
                              if (strcmp($number, $copy_number)) {
                                  $rows[$mfhd_id][$number] = $rows[$mfhd_id][$copy_number];
                                  $deleteThese[$mfhd_id][] = $copy_number;
                              }
                           }
                        }
                    }
                } catch (\Exception $e) {
                    trigger_error(
                        'Poorly Formatted MFHD Record', E_USER_NOTICE
                    );
                }
            }
        }
        foreach ($deleteThese as $mfhd_id => $copy_numbers) {
            foreach ($copy_numbers as $copy_number) {
                unset ($rows[$mfhd_id][$copy_number]);
            }
        }
        return $rows;
    }

    protected function processHoldingRow($sqlRow)
    {
        $row = parent::processHoldingRow($sqlRow);

        ///////////////////////////////
        // Create a clean volume number so we can match these easily during Request First Available
        if (array_key_exists('ITEM_ENUM', $sqlRow) && isset($sqlRow['ITEM_ENUM'])) {
            $enum = strtolower($sqlRow['ITEM_ENUM']);
            $enum = preg_replace('/:/', ',', $enum);
            $enum = preg_replace('/\//', '-', $enum);
            $enum = preg_replace('/,/', ', ', $enum);
            $enum = preg_replace('/\s+/', ' ', $enum);
            $row['volume'] = $enum;
        }
        if (array_key_exists('CHRON', $sqlRow) && isset($sqlRow['CHRON'])) {
            $chron = strtolower($sqlRow['CHRON']);
            $chron = preg_replace('/:/', ',', $chron);
            $chron = preg_replace('/\//', '-', $chron);
            $chron = preg_replace('/\(/', '', $chron);
            $chron = preg_replace('/\)/', '', $chron);
            $row['volume'] .= '(' . $chron . ')';
        }
        ///////////////////////////////

        try {
            $marc = new File_MARC(
                str_replace(["\n", "\r"], '', $row['_fullRow']['RECORD_SEGMENT']),
                File_MARC::SOURCE_STRING
            );
            if ($record = $marc->next()) {
                $labels = array();
                $hyperlink_texts = array();
                $links = array();
                $texts = array();
                $the856s = \CARLI\RecordDriver\SolrMarc::get856s_from_MARC($record);
                foreach ($the856s as $the856) {
                   $labels[] = $the856['label'];
                   $hyperlink_texts[] = $the856['hyperlink_text'];
                   $links[] = $the856['link'];
                   $texts[] = $the856['text'];
                }
                $row['eresource_label'] = $labels;
                $row['eresource_hyperlink_text'] = $hyperlink_texts;
                $row['eresource'] = $links;
                $row['eresource_text'] = $texts;
            }
        } catch (\Exception $e) {
            trigger_error(
                'Poorly Formatted MFHD Record', E_USER_NOTICE
            );
        }
        // Always show the Request Item link (i.e., even before patrons are logged in!)
        $row['addILLRequestLink'] = true;

       // Tack on any EITEM links; treat them like eresources
       if (array_key_exists('EITEM_LINK', $sqlRow) && isset($sqlRow['EITEM_LINK'])) {
           $eitem_link = $sqlRow['EITEM_LINK'];
           $eitem_caption = '';
           if (array_key_exists('EITEM_CAPTION', $sqlRow) && isset($sqlRow['EITEM_CAPTION'])) {
               $eitem_caption = $sqlRow['EITEM_CAPTION'];
           }
           $row['eresource_text'][] = '';
           $row['eresource_hyperlink_text'][] = $eitem_caption;
           $row['eresource'][] = $eitem_link;
       }

//file_put_contents("/usr/local/vufind/holdings.txt", "\n\n******************************HOLDING info\n" . var_export($row, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);
        return $row;
    }


    // Overridden so that we can "fix" the response XML so that it can be loaded into XML
    protected function makeRequest2($hierarchy, $params = false, $mode = 'GET',
        $xml = false
    ) {
        // Build Url Base
        $urlParams = "http://{$this->ws_host}:{$this->ws_port}/{$this->ws_app}";

        // Add Hierarchy
        foreach ($hierarchy as $key => $value) {
            $hierarchyString[] = ($value !== false)
                ? urlencode($key) . '/' . urlencode($value) : urlencode($key);
        }

        // Add Params
        $queryString = [];
        foreach ($params as $key => $param) {
            $queryString[] = urlencode($key) . '=' . urlencode($param);
        }

        // Build Hierarchy
        $urlParams .= '/' . implode('/', $hierarchyString);

        // Build Params
        $urlParams .= '?' . implode('&', $queryString);
//$debug = "urlParams: $urlParams \n\n";

        // Create Proxy Request
        $client = $this->httpService->createClient($urlParams);

        // Add any cookies
        if ($this->cookies) {
            $client->addCookie($this->cookies);
        }

        // Set timeout value
        $timeout = isset($this->config['Catalog']['http_timeout'])
            ? $this->config['Catalog']['http_timeout'] : 30;
        $client->setOptions(['timeout' => $timeout]);

        // Attach XML if necessary
        if ($xml !== false) {
            $client->setEncType('text/xml');
            $client->setRawBody($xml);
//$debug .= "xml: $xml \n\n";
        }
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        // Send Request and Retrieve Response
        $startTime = microtime(true);
        $result = $client->setMethod($mode)->send();
        if (!$result->isSuccess()) {
            $this->error(
                "$mode request for '$urlParams' with contents '$xml' failed: "
                . $result->getStatusCode() . ': ' . $result->getReasonPhrase()
            );
            throw new ILSException('Problem with RESTful API.');
        }

        // Store cookies
        $cookie = $result->getCookie();
        if ($cookie) {
            $this->cookies = $cookie;
        }

        // Process response
        $xmlResponse = $result->getBody();
//$debug = "response to " . $urlParams . "\n\n" . $xmlResponse;
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\n" . var_export($debug, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        ///////////////////////////////////////////////////////////////////////
        // HACK: libxml will simply *NOT* load the XML returned by Voyager API
        // Therefore, we strip the namespace defs and refs
        $xmlResponse = preg_replace('/(<\/*)[^>:]+:/', '$1', $xmlResponse);
        ///////////////////////////////////////////////////////////////////////

        $this->debug(
            '[' . round(microtime(true) - $startTime, 4) . 's]'
            . " $mode request $urlParams, contents:" . PHP_EOL . $xml
            . PHP_EOL . 'response: ' . PHP_EOL
            . $xmlResponse
        );
        $oldLibXML = libxml_use_internal_errors();
        libxml_use_internal_errors(true);
        $simpleXML = @simplexml_load_string($xmlResponse);
        libxml_use_internal_errors($oldLibXML);

        if ($simpleXML === false) {
            return false;
        }
//file_put_contents("/usr/local/vufind/look.txt", "\n\n******************************\nsimpleXML:\n\n" . var_export($simpleXML, true) . "\n******************************\n\n", FILE_APPEND | LOCK_EX);

        return $simpleXML;
    }

    // Disable the Holds & Recalls actions, since we show this information already in Requested Items
    public function supportsMethod($method, $params)
    {
        if ($method == "getMyHolds") {
            return false;
        }
        return parent::supportsMethod($method, $params);
    }

    // Set the default pickup library to that of the patron's library (not the item owning's library)
    // Also, because we want to be able to treat an AAA scenario
    // as if it were a callslip (StorageRetrievalRequest).
    // NOTE: We purposely overrode MultiBackend::getILLPickupLibraries()
    //       to pass the source in the ID because we need this info!)
    public function getILLPickupLibraries($id, $patron)
    {
        // Parse out the source library, e.g., UIUdb.123 => 123
        // (We purposely overrode MultiBackend::getILLPickupLibraries()
        //  to pass the source in the ID because we need this info!
        if (!empty($id) && preg_match('/\./', $id, $matches)) {
            list($source, $id) = explode('.', $id, 2);
        }

        ///////////////////////////////////////////////////////
        // if $id (bib ID) is empty, simply return *all* libraries
        //
        // default is set to patron's library (based on $patron param)
        //
        ///////////////////////////////////////////////////////
        if (empty($id) || $id === '') {
            list($source, $patronId) = explode('.', $patron['id'], 2);

            if (isset($this->config['ILLRequestSources'][$source])) {
                $defaultUbid = $this->config['ILLRequestSources'][$source];
                $results = array();
                foreach ($this->config['ILLRequestSources'] as $code => $ubid) {
                    $result = array();
                    if (preg_match('/^[1@]*(.+)[Dd][Bb]/', $ubid, $matches)) {
                        $item_agency_id = $this->ubCodeToLibCode($ubid);
                        if ($item_agency_id === $this->translate($item_agency_id)) {
                            continue; // skip any that don't have English translations (probably out-of-date ub codes)
                        }
                        $result['id'] = $ubid;
                        $result['name'] = $this->translate($item_agency_id);
                        $result['isDefault'] = $item_agency_id === $source;
                        $results[] = $result;
                    }
                }
            }
            usort($results, function($a, $b) { return strcmp($a{'name'}, $b{'name'}); });
            return $results;
        }
        ///////////////////////////////////////////////////////

        // we stripped out source library because parent class deals only with local bib IDs (numerals only)
        $results = parent::getILLPickupLibraries($id, $patron);
        if ($results === false) {
            // We need to always send back at least one library (the item owning's)
            // so that AAA (callslip) requests can potentially occur
            $results = array();
            $result = array();
            $itemUbId = $this->config['ILLRequestSources'][$source];
            if (preg_match('/^[1@]*(.+)[Dd][Bb]/', $itemUbId, $matches)) {
                //$item_agency_id_lc3 = strtolower($matches[1]);
                //$item_agency_id = strtoupper($item_agency_id_lc3) . 'db';
                $item_agency_id = $this->ubCodeToLibCode($itemUbId);
            }
            $result['id'] = $itemUbId;
            $result['name'] = $this->translate($item_agency_id);
            $result['isDefault'] = true;
            $results[] = $result;
            return $results;
        }

        // determine logged-in patron's UbId
        list($source, $patronId) = explode('.', $patron['id'], 2);
        if (isset($this->config['ILLRequestSources'][$source])) {
            $pickupLibUbId = $this->config['ILLRequestSources'][$source];

            // clear out any default settings and set only the patron's UbId setting
            for ($i = 0; $i < count($results); ++$i) {
               if ($results[$i]['id'] == $pickupLibUbId) {
                   $results[$i]['isDefault'] = true;
               } else {
                   $results[$i]['isDefault'] = false;
               }
            }

            // The results aren't in order. The item owning's library is at the top. Fix this by re-sorting.
            $this->sortByAssocArrayKeyAsc('name', $results);
        }

        return $results;
    }


   // Function described in following article: https://joshtronic.com/2013/09/23/sorting-associative-array-specific-key/
   function sortByAssocArrayKeyAsc($field, &$array)
   {
       usort($array, create_function('$a, $b', '
           $a = $a["' . $field . '"];
           $b = $b["' . $field . '"];

           if ($a == $b) return 0;

           return ($a ' . '<' .' $b) ? -1 : 1;
       '));

       return true;
   }
   function sortByAssocArrayKeyDesc($field, &$array)
   {
       usort($array, create_function('$a, $b', '
           $a = $a["' . $field . '"];
           $b = $b["' . $field . '"];

           if ($a == $b) return 0;

           return ($a ' . '>' .' $b) ? -1 : 1;
       '));

       return true;
   }

   function getChargedStatusCodeAlert($code)
   {
      switch ($code) {
         case '4': return 'Overdue';
         case '5': return 'Recalled';
         case '13': return 'Reported Lost by Patron';
         case '14': return 'Overdue; Assumed Lost';
      }
      return null;
   }


   function getChargedStatusCode($code)
   {
      switch ($code) {
         //case '1': return 'Available';
         case '2': return 'Checked out';
         case '3': return 'Renewed';
         case '4': return 'Overdue';
         case '5': return 'Recalled';
         //case '6': return 'Hold Request';
         //case '7': return 'On Hold';
         //case '8': return 'In Transit';
         //case '9': return 'In Transit Discharged';
         //case '10': return 'In Transit On Hold';
         //case '11': return 'Recently checked in';
         //case '12': return 'Missing';
         case '13': return 'Reported Lost by Patron';
         case '14': return 'Overdue; Assumed Lost';
         //case '15': return 'Claims Returned';
         //case '16': return 'Damaged';
         //case '17': return 'Withdrawn';
         //case '18': return 'At Bindery';
         //case '19': return 'Cataloging Review';
         //case '20': return 'Circulation Review';
         //case '21': return 'Scheduled';
         //case '22': return 'In Process';
         //case '23': return 'Callslip Request';
         //case '24': return 'Short Loan Request';
         //case '25': return 'Remote Storage Request';
      }
      return '';
   }

   function ubCodeToLibCode($ubcode) {
       foreach ($this->config['ILLRequestSources'] as $source => $this_ubcode) {
           if ($ubcode === $this_ubcode) {
               return $source;
           }
       }
       return $ubcode;
   }

    protected function getCallSlips($patron, $local = false)
    {
        // Build Hierarchy
        $hierarchy = [
            'patron' =>  $patron['id'],
            'circulationActions' => 'requests',
            'callslips' => false
        ];

        // Add Required Params
        $params = [
            'patron_homedb' => $this->ws_patronHomeUbId,
            'view' => 'full'
        ];

        $results = $this->makeRequest($hierarchy, $params);

        $replyCode = (string)$results->{'reply-code'};
        if ($replyCode != 0 && $replyCode != 8) {
            throw new Exception('System error fetching call slips');
        }
        $requests = [];
        if (isset($results->callslips->institution)) {
            foreach ($results->callslips->institution as $institution) {
                if (!$local
                    && $this->isLocalInst((string)$institution->attributes()->id)
                ) {
                    // Unless $local is set, ignore local callslips; we have them
                    // already....
                    continue;
                }
                foreach ($institution->callslip as $callslip) {
                    $item = $callslip->requestItem;
                    $requests[] = [
                        'id' => '',
                        'status' => (string)$item->status, // CARLI ADDED
                        'status_text' => (string)$item->statusText, // CARLI ADDED
                        'type' => (string)$item->holdType,
                        'location' => (string)$item->pickupLocation,
                        'expire' => (string)$item->expiredDate
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d', (string)$item->expiredDate
                            )
                            : '',
                        // Looks like expired date shows creation date for
                        // call slip requests, but who knows
                        'create' => (string)$item->expiredDate
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d', (string)$item->expiredDate
                            )
                            : '',
                        'position' => (string)$item->queuePosition,
                        'available' => (string)$item->status == '4',
                        'reqnum' => (string)$item->holdRecallId,
                        'item_id' => (string)$item->itemId,
                        'volume' => '',
                        'publication_year' => '',
                        'title' => (string)$item->itemTitle,
                        'institution_id' => (string)$institution->attributes()->id,
                        'institution_name' => (string)$item->dbName,
                        'institution_dbkey' => (string)$item->dbKey,
                        'processed' => substr((string)$item->statusText, 0, 6)
                            == 'Filled'
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d', substr((string)$item->statusText, 7)
                            )
                            : '',
                        'canceled' => substr((string)$item->statusText, 0, 8)
                            == 'Canceled'
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d', substr((string)$item->statusText, 9)
                            )
                            : ''
                    ];
                }
            }
        }
        return $requests;
    }

    protected function getHoldsFromApi($patron, $local)
    {
        // Build Hierarchy
        $hierarchy = [
            'patron' =>  $patron['id'],
            'circulationActions' => 'requests',
            'holds' => false
        ];

        // Add Required Params
        $params = [
            'patron_homedb' => $this->ws_patronHomeUbId,
            'view' => 'full'
        ];

        $results = $this->makeRequest($hierarchy, $params);

        if ($results === false) {
            throw new ILSException('System error fetching remote holds');
        }

        $replyCode = (string)$results->{'reply-code'};
        if ($replyCode != 0 && $replyCode != 8) {
            throw new ILSException('System error fetching remote holds');
        }
        $holds = [];
        if (isset($results->holds->institution)) {
            foreach ($results->holds->institution as $institution) {
                // Filter by the $local parameter
                $isLocal = $this->isLocalInst(
                    (string)$institution->attributes()->id
                );
                if ($local != $isLocal) {
                    continue;
                }

                foreach ($institution->hold as $hold) {
                    $item = $hold->requestItem;

                    $holds[] = [
                        'id' => '',
                        'status' => (string)$item->status, // CARLI ADDED
                        'status_text' => (string)$item->statusText, // CARLI ADDED
                        'type' => (string)$item->holdType,
                        'location' => (string)$item->pickupLocation,
                        'expire' => (string)$item->expiredDate
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d', (string)$item->expiredDate
                            )
                            : '',
                        // Looks like expired date shows creation date for
                        // UB requests, but who knows
                        'create' => (string)$item->expiredDate
                            ? $this->dateFormat->convertToDisplayDate(
                                'Y-m-d', (string)$item->expiredDate
                            )
                            : '',
                        'position' => (string)$item->queuePosition,
                        'available' => (string)$item->status == '2',
                        'reqnum' => (string)$item->holdRecallId,
                        'item_id' => (string)$item->itemId,
                        'volume' => '',
                        'publication_year' => '',
                        'title' => (string)$item->itemTitle,
                        'institution_id' => (string)$institution->attributes()->id,
                        'institution_name' => (string)$item->dbName,
                        'institution_dbkey' => (string)$item->dbKey,
                        'in_transit' => (substr((string)$item->statusText, 0, 13)
                            == 'In transit to')
                          ? substr((string)$item->statusText, 14)
                          : ''
                    ];
                }
            }
        }
        return $holds;
    }
}

