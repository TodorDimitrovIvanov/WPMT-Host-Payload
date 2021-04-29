<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("error_log", 'wp-multitool/err_log.php');


# Here we set the URL from the repo where MySQLDump, WP Cli, etc. scripts are stored
$config_url_mysqldump = "http://todorivanov.net/repo/mysqldump.tar.gz";
$cofnig_url_wpcli = "http://todorivanov.net/repo/wp-cli.tar.gz";
$runtime_path_root = "";
$runtime_path_wpconfig = "";
$runtime_path_wpcli = "";
$runtime_db_settings = array();

function search_wp_config(){
    #Source: https://www.php.net/manual/en/function.scandir.php
    # Here we get a list of all the files and folders in the root dir of the script
    $dir_scan = scandir(__DIR__);
    foreach ($dir_scan as $item){
            # Here we check whether the list item matches the wp-config file 
            if ($item == "wp-config.php"){
                # If a match is found we save it to the global $runtime_path_wpconfig and $runtime_path_root variables
                global $runtime_path_wpconfig;
                global $runtime_path_root;
                $runtime_path_wpconfig = __DIR__ . "/" . $item;
                $runtime_path_root = __DIR__;
            }
        }
}

#
#   Database functions
#
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
        }
        else{
            # TODO: Report inablity to download the MySQLDump script -> could be a connectivity problem
        }
    }
    # If else the file is already downloaded
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
    # TODO: Report the name of the db.sql file that was generated
}

#
#   File functions
#
function files_permission_fix(){
    
}

#
#   WordPress functions
#
function wp_setup_cli(){
    # Here we define the URL for the repo to WP Cli
    global $cofnig_url_wpcli;
    global $runtime_path_wpcli;
    global $runtime_path_root;
    # And get the name of the file from the repo
    $file_wpcli = basename($cofnig_url_wpcli);
    if (!file_exists('wp-multitool/wp-cli')){
        mkdir('wp-multitool/wp-cli', 0755, true);
    }
    # Here we check if the WP Cli is already downloaded
    if (!file_exists('wp-multitool/wp-cli/wp-cli.phar')){
        # In case the file doesn't exist we download it from the repo URL
        if (file_put_contents('wp-multitool/' . $file_wpcli, file_get_contents($cofnig_url_wpcli))){
            # Here we decompress the .gz archive 
            $file_wpcli_decompressed = new PharData('wp-multitool/' . $file_wpcli);
            $file_wpcli_decompressed -> decompress();
            # And next we extract the file from the archive
            $file_wpcli_extracted = new PharData('wp-multitool/' . $file_wpcli);
            $file_wpcli_extracted -> extractTo('wp-multitool/wp-cli');
            # TODO: Report wp-cli.phar location
        }
        else{
            # TODO: Report inablity to download wp-cli 
        }
    }
    else{
        # If the wp-cli.phar is already downloaded:
        $runtime_path_wpcli = $runtime_path_root . 'wp-multitool/wp-cli/wp-cli.phar';
    }
    
}

function wp_core_version_get(){
    global $runtime_path_wpcli;
    global $runtime_path_root;
    $command = $runtime_path_wpcli . "core version --path=" . $runtime_path_root;
    $output = shell_exec($command);
}

function wp_plugin_list(){

}

function wp_theme_list(){

}

function wp_cache_flush(){

}



search_wp_config();
db_setup_mysqldump();
wp_setup_cli();
db_get_settings();
db_generate_backup();



#
#   Routing 
#
# Source: https://stackoverflow.com/questions/4360182/call-php-function-from-url
# Source: https://stackoverflow.com/a/4360206
# Here we check the requests that are received by the script
switch($_GET['function']){
    case 'wp-setup':
        # Route: wp-multitool.php?function=wp-setup
        wp_setup_cli();
    case 'db-setup':
        # Route: wp-multitool.php?function=db-setup
        db_setup_mysqldump();
    case 'wp-core-version-get':
        # Route: wp-multitool.php?function=wp-core-version-get
        wp_core_version_get();
}