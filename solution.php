<?php

define("WORD_MIN_COMPARE_LENGTH", 3);
define("MAX_DUPLICATE_COUNT", 500);
define("ASK_USER", false);

class Keyword
{
    public $count;
    public $companies = array();

    function __construct($index)
    {
        $this->count       = 1;
        $this->companies[] = $index;
    }
}
;

class Marker
{
    public function readData($cvs_file)
    {
        $fp   = fopen($cvs_file, "r");
        $rows = array();

        while (($row = fgetcsv($fp, 1000, "\t")) !== FALSE) {
            $rows[] = $row;
        }
        fclose($fp);

        return $rows;
    }

    public function getMarkedTable($rows)
    {
        $r                  = 0;
        $keywords           = array();
        $optimized_keywords = array();

        echo "Analyzing data ...\n";

        foreach ($rows as $row) {
            // Get Company Name
            $company = trim($row[1]);

            // Get all the keywords in this company name
            $tokens = explode(" ", $company);

            for ($i = 0; $i < count($tokens); $i++) {
                $keyword = strtolower(trim($tokens[$i]));

                if (strlen($keyword) >= WORD_MIN_COMPARE_LENGTH) {
                    // If this keyword didn't exist in the keywords hashtable, then create a new keyword in the hashtable
                    if (!isset($keywords[$keyword]))
                        $keywords[$keyword] = new Keyword($r);
                    else {
                        // otherwise get that existing keyword, increment its count of occurrances and add company index (of original rows) in
                        // its hashtable (that actually contained this keyword)

                        $obj_keyword = $keywords[$keyword];
                        $obj_keyword->count++;
                        $obj_keyword->companies[] = $r; // Just set that it exists in the hash with O(1) access.

                        // If the keyword is existing in more than 1 company names, then save it in the optimized_keywords hash.
                        $optimized_keywords[$keyword] = $obj_keyword;
                    }
                }
            }
            $r++;
        }

        // now we have a hashtable of keywords (with references to company names) where these keywords exist
        // We simply iterate over these keywords and compare their company names with text_match to see how much they compare.

        echo "Finding matches ...\n";

        $marked_rows = array();

        $k = 0;
        foreach ($optimized_keywords as $keyword => $optkey) {
            $companies = $optkey->companies;

            $count = count($companies);

            // We ignore those keywords which exist so many times. We rely on other keywords in those names
            if ($count < MAX_DUPLICATE_COUNT) {
                for ($i = 0; $i < $count; $i++) {
                    $ith_company_index = $companies[$i];
                    for ($j = $i + 1; $j < $count; $j++) {
                        $jth_company_index = $companies[$j];

                        if ($ith_company_index != $jth_company_index) {
                            $ith_company_name = trim($rows[$ith_company_index][1]);
                            $jth_company_name = trim($rows[$jth_company_index][1]);

                            $percent = 0;
                            similar_text($ith_company_name, $jth_company_name, $percent);

                            if ($percent > 80) {
                                if ($this->match_addresses($rows[$ith_company_index], $rows[$jth_company_index])) {
                                    $marked_rows[$ith_company_index] = $jth_company_index;
                                    $marked_rows[$jth_company_index] = $ith_company_index;
                                }
                            }
                            if ($percent > 50) {
                                if (ASK_USER) {
                                    $input = "";

                                    do {
                                        echo "Do you think the company names '" . $ith_company_name . "' and '" . $jth_company_name . "' match? \nType y for yes and n for no:\n";
                                        $input = trim(chop(fgets(STDIN)));

                                        if (strcmp(strtolower($input), "y") == 0) {
                                            if ($this->match_addresses($rows[$ith_company_index], $rows[$jth_company_index])) {
                                                $marked_rows[$ith_company_index] = $jth_company_index;
                                                $marked_rows[$jth_company_index] = $ith_company_index;
                                            }
                                        }
                                    } while ((strcmp(strtolower($input), "y") != 0) && (strcmp(strtolower($input), "n") != 0));
                                }
                            }
                        }
                    }
                }
            }
            $k++;
        }

        echo "Generating table of duplicates...\n";

        $table  = "<table border=1>\n";
        $header = $rows[0];
        $table  = $table . "\t<tr>\n";
        $table  = $table . "\t\t<td><span style='font-weight:bold'>S.No.</span></td>\n";
        $table  = $table . "\t\t<td><span style='font-weight:bold'>Matches with</span></td>\n";
        foreach ($header as $key => $caption) {
            $table = $table . "\t\t<td><span style='font-weight:bold'>" . $caption . "</span></td>\n";
        }
        $table = $table . "\t</tr>\n";

        foreach ($marked_rows as $row_index => $matched_row_index) {
            $row = $rows[$row_index];

            // Here find the color of the row either "yellow" if it was taken from yellow pages or grey if it is a real customer

            $color        = "#ffffff";
            $cust_this    = strtolower(trim($rows[$row_index][14]));
            $cust_matched = strtolower(trim($rows[$matched_row_index][14]));

            if ($this->startsWith($cust_this, "abo") && $this->startsWith($cust_matched, "gs")) {
                $color = "#cccccc";
            } else if ($this->startsWith($cust_this, "gs")) {
                $color = "#ffff00";
            }


            $table = $table . "\t<tr bgColor='" . $color . "'>\n";
            $table = $table . "\t\t<td><span>" . $row_index . "</span></td>\n";
            $table = $table . "\t\t<td><span>" . $matched_row_index . "</span></td>\n";
            foreach ($row as $caption => $data) {
                $table = $table . "\t\t<td><span>" . $data . "</span></td>\n";
            }
            $table = $table . "\t</tr>\n";
        }
        $table = $table . "</table>\n";

        return $table;
    }

    public function match_addresses($rowi, $rowj)
    {
        // Do some cleaning and then compare the addresses.
        for ($i = 4; $i <= 9; $i++) {
            $rowi[$i] = (strcmp(trim($rowi[$i]), "NULL") == 0) ? "" : $rowi[$i];
            $rowj[$i] = (strcmp(trim($rowj[$i]), "NULL") == 0) ? "" : $rowj[$i];
        }
        return ((strcmp(strtolower(trim($rowi[4])), strtolower(trim($rowj[4]))) == 0) && (strcmp(strtolower(trim($rowi[5])), strtolower(trim($rowj[5]))) == 0) && (strcmp(strtolower(trim($rowi[6])), strtolower(trim($rowj[6]))) == 0) && (strcmp(strtolower(trim($rowi[7])), strtolower(trim($rowj[7]))) == 0) && (strcmp(strtolower(trim($rowi[8])), strtolower(trim($rowj[8]))) == 0) && (strcmp(strtolower(trim($rowi[9])), strtolower(trim($rowj[9]))) == 0));
    }
    function startsWith($haystack, $needle, $case = true)
    {
        if ($case)
            return strncasecmp($haystack, $needle, strlen($needle)) == 0;
        else
            return strncmp($haystack, $needle, strlen($needle)) == 0;
    }
    public function mark($inputfile, $outputfile)
    {
        $rows = $this->readData($inputfile);

        $table = $this->getMarkedTable($rows);

        $html = "<html><body>" . $table . "</body></html>";

        $fh = fopen($outputfile, 'w') or die("can't open file");

        fwrite($fh, $html);
    }
}
;

$marker = new Marker();
$marker->mark("Sub-Companies.csv", "output.html");

?>