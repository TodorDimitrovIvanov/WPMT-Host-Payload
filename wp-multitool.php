<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("error_log", 'wp-multitool/err_log.php');



# Here we set the URL from the repo where the MySQLDump script is stored
$config_url_mysqldump = "http://todorivanov.eu/repo/mysqldump.tar.gz";
$cofnig_url_wpcli = "http://todorivanov.eu/repo/wp-cli.tar.gz";
$runtime_path_wpconfig = "";
$runtime_db_settings = array();

function search_wp_config(){
    #Source: https://www.php.net/manual/en/function.scandir.php
    # Here we get a list of all the files and folders in the root dir of the script
    $dir_scan = scandir(__DIR__);
    foreach ($dir_scan as $item){
            # Here we check whether the list item matches the wp-config file 
            if ($item == "wp-config.php"){
                # If a match is found we save it to the global $runtime_path_wpconfig variable
                global $runtime_path_wpconfig;
                $runtime_path_wpconfig = __DIR__ . "/" . $item;
            }
        }
}

function db_get_settings(){
        #Source: https://stackoverflow.com/questions/3686177/php-to-search-within-txt-file-and-echo-the-whole-line
        #Source: https://www.php.net/manual/en/function.preg-match-all.php
        #Source: https://www.oreilly.com/library/view/php-cookbook/1565926811/ch13s07.html
        global $runtime_path_wpconfig;
        global $runtime_db_settings;
        # Here we open and save the wp-config.php file into the $wpconfig_opened file
        $wpconfig_opened = file_get_contents($runtime_path_wpconfig);
        # Here we prepare a list of strings to be searched later 
        $search_list = [ "DB_NAME", "DB_USER", "DB_PASSWORD", "DB_HOST" ];
        for ($i = 0; $i<count($search_list); $i++){
            # PHP IS A FUCKING STUPID LANGUAGE!!!! 
            # WHY THE FUCK SHOULD THERE BE DELIMETERS AT THE START AND END OF REGEX?????
            # Source: https://stackoverflow.com/questions/27771852/preg-match-no-ending-delimiter 
            # And the pattern we'll use to search for the data
            $pattern = '~\'(?<type>' . $search_list[$i]. ')\', ?\'(?<match>.*)\'~';
            # Here we check for the match
            if (preg_match_all( $pattern, $wpconfig_opened, $temp)){
                # PHP is fucking stupid ....
                $temp_type=$temp['type'][0];
                $temp_match=$temp['match'][0];
                # And then save the match and the type to the $runtime_db_settings associatve array 
                $runtime_db_settings[$temp_type] = $temp_match;
            }
            else{
                # TODO: Report inablity to find DB settings -> could be a custom wp-config.php file
                echo "Found nothing!\n";
            }
        }
}

function db_setup_mysqldump(){
    # Source: https://www.geeksforgeeks.org/download-file-from-url-using-php/
    # Here we declare that we'll be accessing the global $config_url_mysqldump variable
    global $config_url_mysqldump;
    # Here we get the name of the file from the URL 
    $file_mysqldump = basename($config_url_mysqldump);
    # Source: https://stackoverflow.com/questions/2303372/create-a-folder-if-it-doesnt-already-exist
    # Here we make an empty directory:
    if (!file_exists('wp-multitool/mysqldump')){
        mkdir('wp-multitool/mysqldump', 0755, true);
    }
    # Here we check whether the mysqldump script is already downloaded
    if (!file_exists('wp-multitool/mysqldump.tar.gz') | !file_exists('wp-multitool/mysqldump.tar')){
        # Here we download the file from the URL
        if (file_put_contents( 'wp-multitool/' . $file_mysqldump, file_get_contents($config_url_mysqldump))){
            # Source: https://stackoverflow.com/questions/9416508/php-untar-gz-without-exec
            # Here we decompress the .gz archive 
            $file_mysqldump_decompressed = new PharData('wp-multitool/' . $file_mysqldump);
            $file_mysqldump_decompressed -> decompress();
            # Here we extract the .tar archive
            $file_mysqldump_extracted = new PharData('wp-multitool/' . $file_mysqldump);
            $file_mysqldump_extracted -> extractTo('wp-multitool/mysqldump');
            echo "File " . $file_mysqldump . " downloaded successfully";
        }
        else{
            echo "File downloading failed";
        }
    }
    else{
        echo "File already downloaded";
    } 
}

function db_generate_backup(){
    include 'wp-multitool/mysqldump/src/Ifsnop/Mysqldump/Mysqldump.php';
    global $runtime_db_settings;
    $temp_host = $runtime_db_settings['DB_HOST'];
    $temp_db = $runtime_db_settings['DB_NAME'];
    $dump = new Ifsnop\Mysqldump\Mysqldump("mysql:host=$temp_host;dbname=$temp_db", $runtime_db_settings['DB_USER'], $runtime_db_settings['DB_PASSWORD']);
    if (!file_exists('wp-multitool/backups/mysql')){
        mkdir('wp-multitool/backups/mysql', 0755, true);
    }
    $date = date("Y-m-d-h-i");
    $dump->start('wp-multitool/backups/mysql/'. $runtime_db_settings['DB_NAME'] . '-' . $date . '.sql');
    echo "Database backup generated!";
}




search_wp_config();
db_setup_mysqldump();
db_get_settings();
db_generate_backup();
print_r($runtime_db_settings);