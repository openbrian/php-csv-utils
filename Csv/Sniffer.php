<?php
/**
 * CSV Utils - Sniffer
 * 
 * This class accepts a sample of csv and attempts to deduce its format. It then
 * can return a Csv_Dialect tailored to that particular csv file
 * Please read the LICENSE file
 * @copyright MC2 Design Group, Inc. <luke@mc2design.com>
 * @author Luke Visinoni <luke@mc2design.com>
 * @package Csv
 * @license GNU Lesser General Public License
 * @version 0.1
 */
require_once 'Csv/Exception/CannotDetermineDialect.php';
require_once 'Csv/Exception/DataSampleTooShort.php';
require_once 'Csv/Reader/String.php';
/**
 * Attempts to deduce the format of a csv file
 * 
 * @package Csv
 */
class Csv_Sniffer
{
    /**
     * Attempts to deduce the format of a sample of a csv file and returns a dialect object
     * eventually it will throw an exception if it can't deduce the format, but for now it just
     * returns the basic csv dialect
     * 
     * @param string A piece of sample data used to deduce the format of the csv file
     * @return array An array with the first value being the quote char and the second the delim
     * @access protected
     */
    public function sniff($data) {
        
        list($quote, $delim) = $this->guessQuoteAndDelim($data);
        if (is_null($delim)) {
            if ($delim = $this->guessDelim($data)) {
                $dialect = new Csv_Dialect();
                $dialect->delimiter = $delim;
                if (!$quote) {
                    // @todo: figure out if this is the best way to go about this
                    $dialect->quotechar = '"';
                    $dialect->quoting = Csv_Dialect::QUOTE_NONE;
                }
                return $dialect;
            }
        }
        throw new Csv_Exception_CannotDetermineDialect('Csv_Sniffer was unable to determine the file\'s dialect.');
    
    }
    /**
     * Determines if a csv sample has a header row - not 100% accurate by any means
     * It basically looks at each row in each column. If all but the first column are similar, 
     * it likely has a header. The way we determine this is first by type, then by length
     * Other possible methods I could use to determine whether the first row is a header is I
     * could look to see if all but the first CONTAIN certain characters or something - think about this
     */
    public function has_header($data) {
    
        $reader = new Csv_Reader_String($data, $this->sniff($data));
        list($checked, $types, $lengths, $total_lines, $headers) = array(0, array(), array(), $reader->count(), $reader->getRow());
        $total_columns = count($headers);
        foreach (range(0, $total_columns-1) as $key => $col) $types[$col] = null;
        // loop through each remaining rows
        while ($row = $reader->current()) {
            // no need to check more than 20 lines
            if ($checked > 20) break; $checked++;
            $line = $reader->key();
            // loop through the types array (use as column)
            
            // if ($this->getType($row[$key]) == $this->getType($headers[$key])) echo "WOW";
            $reader->next();
        }
        //pr($headers, $reader->asArray());
        
        // now take a vote and if more than a certain threshold have a likely header, we'll return that we think it has a header
    
    }
    
    protected function getType($value) {
    
        switch (true) {
            case ctype_digit($value):
                return "integer";
            case preg_match("/^[array()-9\.]$/i", $value, $matches):
                return "double";
            case ctype_alnum($value):
            default:
                return "string";
        }
    
    }
    
    
    /**
     * I copied this functionality from python's csv module. Basically, it looks
     * for text enclosed by identical quote characters which are in turn surrounded
     * by identical characters (the probable delimiter). If there is no quotes, the
     * delimiter cannot be determined this way.
     *
     * @param string A piece of sample data used to deduce the format of the csv file
     * @return array An array with the first value being the quote char and the second the delim
     * @access protected
     */
    protected function guessQuoteAndDelim($data) {
    
        $patterns = array();
        $patterns[] = '/([^\w\n"\']) ?(["\']).*?(\2)(\1)/'; 
        $patterns[] = '/(?:^|\n)(["\']).*?(\1)([^\w\n"\']) ?/'; // dont know if any of the regexes starting here work properly
        $patterns[] = '/([^\w\n"\']) ?(["\']).*?(\2)(?:^|\n)/';
        $patterns[] = '/(?:^|\n)(["\']).*?(\2)(?:$|\n)/';
        
        foreach ($patterns as $pattern) {
            if ($nummatches = preg_match_all($pattern, $data, $matches)) {
                if ($matches) break;
            }
        }
        
        if (!$matches) return array("", null); // couldn't guess quote or delim
        
        $quotes = array_count_values($matches[2]);
        arsort($quotes);
        if ($quote = array_shift(array_flip($quotes))) {
            $delims = array_count_values($matches[1]);
            arsort($delims);
            $delim = array_shift(array_flip($delims));
        } else {
            $quote = ""; $delim = null;
        }
        return array($quote, $delim);
    
    }
    /**
     * Attempts to guess the delimiter of a set of data
     *
     * @param string The data you would like to get the delimiter of
     */
    protected function guessDelim($data) {
    
        // count every character on every line
        $data = explode("\n", $data);
        if (count($data) < 10) throw new Csv_Exception_DataSampleTooShort('You must provide at least ten lines in your sample data');
        
        $frequency = array();
        foreach ($data as $row) {
            if (empty($row)) continue;
            $frequency[] = count_chars($row, 1);
        }
        
        // determine the "mode" for each character
        $modes = array();
        foreach ($frequency as $line) {
            foreach ($line as $char => $count) {
                //$ord = ord($char);
                if (!isset($modes[$char]) || $count > $modes[$char])
                    $modes[$char] = $count;
            }
        }
        // count how many times each character matches its mode in a line
        $temp = array();
        foreach ($modes as $key => $mode) {
            foreach ($frequency as $line) {
                if (!isset($temp[chr($key)])) $temp[chr($key)] = 0;
                if (isset($line[$key]) && $line[$key] == $mode) $temp[chr($key)]++; 
            }
        }
        
        arsort($temp);
        $times_matched = current($temp);
        $lines = count($data);
        $consistency = $times_matched / $lines;
        $threshold = 0.9;
        // if it is consistent enough, return the delimiter we think it is
        $delim = key($temp);
        if ($consistency > $threshold) return $delim;
        return false;
    
    }
}