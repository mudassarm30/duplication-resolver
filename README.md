# Problem Statement

## Duplicates

You can find the data in the file Sub-Companies.csv.
We want you to compare the entries and mark the duplicates. Duplicates are
entries that have the same company name and address. But other data is of
course relevant too.
It’s possible that the names of the companies are not exactly identical but are
still the same company. An idea is to use a function to search for phonetic
similarities, but you could use something else of course. Sometimes company
names are identical and the address is not matching because they are company
branches – then they are not duplicates.
My idea would be to do a scoring system to show what percentage of the data is
the same, for example. If less than 50% of data match, I wouldn’t mark it. If more
than 80% of data match, it’s a duplicate. For everything in between or if I am not
completely sure if the two entries are duplicates, I would let the user decide with
a simple user interface, if it should be marked or not. But if you have another
idea, you are welcome to do it and present it.
In the column CustomerNo you see our customer numbers. The ones that start
with Abo are our actual customers and the ones that start with GS are the one we
got from yellow pages. If you find a company, where the customer number starts
with Abo, with a duplicate in yellow pages, you should mark it somehow special.
The reason is that we send out advertisement emails to companies from yellow
pages, but we don’t want to do that if the company is already our customer.
We would like you to send us a short precise description of your thoughts,
solution and the way how you got to it. Please send it together with the script.

## Solution Description

The code written for this task is quite flexible and we can parametrize it for
execution time or for result quality. The overall algorithm (pseudo code) is given
below.

Function: getMarkedTable( [in] rows ) returns html table (of match rows)
  for each row r
    company_name = r[“Company”];
    for each keyword k in company_name with length > alpha
      if(k exists in hash)
        increment hash[k].count
        insert (hash[k].company_indices_of_rows, r.index)
        optimized_hash[k] = hash[k];
      else
        hash[k] = new keyword( count = 1, company_index = r.index );
      end if
  end for
  marked_rows = new hash();
  for each keyword k in optimized_hash
    if(optimized_hash[k].count < beta)
      for any pair (a, b) in optimized_hash[k].company_indices_of_rows
        if(similarity(rows[a][“Company”], rows[b][“Company”]) > 80%)
          if(match_addresses(rows[a], rows[b]) == true)
            marked_rows[a] = b;
            marked_rows[b] = a;
          end if
        else if (similarity(rows[a][“Company”], rows[b][“Company”]) > 50%)
          if(user prompt is enable)
             Ask user if rows[a][“Company”] and rows[b][“Company”] match?
             If given yes
               if(match_addresses(rows[a], rows[b]) == true)
                  marked_rows[a] = b;
                  marked_rows[b] = a;
               end if
            end if
          end if
        end if
      end for
    end if
  end for
  html_table = new table;
  for each pair (current_row_index => match_row_index) in marked_rows
    if( strtolower(rows[current_row_index][“CustomerNo”]) starts with “abo” and strtolower(rows[matched_row_index][“CustomerNo”]) starts with “ge”)
      color = GREY
    else if ( strtolower(rows[current_row_index][“CustomerNo”]) starts with “ge”)
      color = YELLOW
    end if
    html_table.insert(rows[current_row_index] in proper formatting and color)
  end for
  return html_table;
End Function

## The Algorithm

In simple words the algorithm actually creates a hash table of keywords
that exist in some company names. At each entry in the hash (where key is
actually the keyword for O(1) access), we maintain the count of companies
where the given keyword exists and the hash table of indices of those
company rows in the original rows (that are provided in argument). More
than 99% of keywords existed in very few company names. We ignored
very few keywords (that exists in N number of company names where N is
in thousands) with the assumption that if there is possible 50+ % or 80%
match then it will be tackled by other keywords in those names (that exist
in them). This optimization actually results in comparison of very very few
company names that may be potential matches. We save the indices of
those rows that became actual matches and using those indices (that are
match 80% or 50% with users intervention) we get the final table in html
format that actually comprises the matched rows. We tackle the Customer
type etc as required.
Important Points:
The important things to note are

   The alpha, beta and the option to prompt the user for comparison can be
    given to the program
  
   The prompt is made optional because sometimes we may want to see the
    result for 80%+ matches

   The alpha is actually the minimum length of the word that keyword that
    should take part in the comparison, we can make it 1 if we are interested
    to see more accuracy (program parameter).

   The beta is the maximum number of company names in which a given
    keyword is existing. I observed that in three cases the number of
    companies where a keyword existed were 500, 900 and 4500. I kept the
    maximum count to 500 (in the program, but can be given as parameter for
    more accuracy). There is another observation in this point that, if a
    keyword, that exists in 4500 company names, is ignored for which few of
    companies (where this keyword existed) were actually matching more than
    80% then other keywords (whose count might be very less, like, 2, 3, 10
    etc) will make it sure that these companies (rows) are marked. So ignoring
    a keyword that exists in 4500 company names didn't effect the quality but
    reduced the execution time considerably.

   I have shown those rows in the final html that actually have matches in the
    actual cv file. I have added two columns (first two) that actually show the
    row number and matching row number in the actual cvs file.
    In the first try, I tried to write the algorithm in a brute force way but that
    was so much time consuming and was not flexible. This program improves the
    performance with reasonable quality of results. Although we can increase the
    execution time and accuracy by setting the parameters.

## The Commented Code

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
