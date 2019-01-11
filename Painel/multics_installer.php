<?php

if ( ! $argc )
{
    exit( "You Can Only Run This Script From CMD" );
}

$we_root = trim( shell_exec( "whoami" ) );
if ( $we_root != "root" )
{
    echo "You have to run this Script as ROOT";
    exit;
}

if ( ! extension_loaded( "mysql" ) )
{
    echo "Please install the mysql extension with: apt-get install php5-mysql";
    exit;
}


echo "\n###############################\n";
echo "#         MultiCS Panel         #\n";
echo "#################################\n\n";

echo "~~ Welcome to MultiCS Panel Auto Installer! This wizard will help you to install the MultiCS Panel automatically.\n\n";


$zip_file = dirname(__FILE__) . "/multics_panel.zip";

if ( ! file_exists( $zip_file ) )
{
    exit( "multics_panel.zip File Does not exists. Put it in the same directory as the installer and run the installer again!\n\n" );
}

echo "[+] Installing dependencies...\n";
system( "apt-get update > /dev/null 2>&1" );
system( "apt-get install sudo apache2 -y > /dev/null 2>&1" );
system( "apt-get -qq install php5 libapache2-mod-php5 php5-mysql php5-curl php5-gd php5-idn php-pear php5-imagick php5-imap php5-mcrypt php5-memcache php5-mhash php5-ming php5-ps php5-pspell php5-recode php5-snmp php5-sqlite php5-tidy php5-xmlrpc php5-xsl php5-json php5-dev php5-curl -qy > /dev/null 2>&1" );
system( "apt-get install unzip -y > /dev/null 2>&1" );
system( "apt-get install wget -y > /dev/null 2>&1" );

$uname = posix_uname();
$machine_arch = $uname['machine'];
if ( stristr( $machine_arch, '64' ) )
{
    $machine_arch = "x64";
}
else
    $machine_arch = "x86";


$php_version = phpversion();
if ( stristr( $php_version, "5.3." ) )
{
    $php_version = "5.3";
}
elseif ( stristr( $php_version, "5.4." ) )
{
    $php_version = "5.4";
}
elseif ( stristr( $php_version, "5.5." ) )
{
    $php_version = "5.5";
}
else
{
    exit( "Unsupported version of php" );
}

//check if extension is already loaded
$php_inis = array( "/etc/php5/cli/php.ini", "/etc/php5/apache2/php.ini" );

foreach ( $php_inis as $php_ini )
{
    $source = file_get_contents( $php_ini );
    if ( ! stristr( $source, "xtream_codes.so" ) )
    {
        system( "echo pcre.backtrack_limit=10000000000 >> $php_ini" );
        system( "echo extension=mcrypt.so >> $php_ini" );
        system( "echo extension=/etc/xtream_codes.so >> $php_ini" );
    }
}

//extension exists?
if ( ! file_exists( "/etc/xtream_codes.so" ) )
{
    $url = dirname(__FILE__) . "/multics_extension/{$machine_arch}_PHP{$php_version}.zip";
    chdir( "/etc/" );
    system( "cp -f $url /etc/xtream_codes.zip && unzip xtream_codes.zip && rm xtream_codes.zip > /dev/null 2>&1" );
}


$mysql_available = trim( shell_exec( "command -v mysql" ) );
if ( empty( $mysql_available ) )
{
    echo "[+] Installing MySQL...\n";
    do
    {
        echo "[+] Please write your desired MySQL Root Password(Minimum: 5 chars): ";
        fscanf( STDIN, '%s', $mysql_root_pass );
        $mysql_root_pass = trim( $mysql_root_pass );
    } while ( strlen( $mysql_root_pass ) < 5 );

    system( "echo mysql-server mysql-server/root_password password $mysql_root_pass | sudo debconf-set-selections > /dev/null 2>&1" );
    system( "echo mysql-server mysql-server/root_password_again password $mysql_root_pass | sudo debconf-set-selections > /dev/null 2>&1" );
    system( "apt-get install mysql-server mysql-client -y > /dev/null 2>&1" );
}
else
{
    echo "[*] Please Enter your Current MySQL Root Password: ";

    do
    {
        fscanf( STDIN, "%s", $mysql_root_pass );
        $mysql_root_pass = trim( $mysql_root_pass );
        $con = @mysql_connect( "localhost", "root", $mysql_root_pass );

    } while ( ! $con );
    mysql_close( $con );
}


do
{
    echo "[*] In which DIR do you want to install the panel? (Default: /var/www/): ";
    fscanf( STDIN, '%s', $install_dir );
} while ( ! is_dir( $install_dir ) );


chdir( $install_dir );
echo "[+] Installing MultiCS Panel...\n";

system( "mv $zip_file ./" );
system( "unzip -o multics_panel.zip > /dev/null 2>&1" );

#Create Database & User
$database_name = "multics_dbv2";
$user = "user_multics";
$password = GenerateString( 10 );

system( "mysql -u root -p$mysql_root_pass -e \"DROP DATABASE IF EXISTS $database_name\" > /dev/null 2>&1" );
system( "mysql -u root -p$mysql_root_pass -e \"DROP USER '$user'@'localhost';\" > /dev/null 2>&1" );
system( "mysql -u root -p$mysql_root_pass -e \"CREATE DATABASE $database_name\" > /dev/null 2>&1" );
system( "mysql -u root -p$mysql_root_pass -e \"CREATE USER '$user'@'localhost' IDENTIFIED BY '$password';\" > /dev/null 2>&1" );
system( "mysql -u root -p$mysql_root_pass -e \"GRANT ALL PRIVILEGES ON $database_name.* TO '$user'@'localhost';\" > /dev/null 2>&1" );
system( "mysql -u root -p$mysql_root_pass -e \"FLUSH PRIVILEGES\" > /dev/null 2>&1" );

#Replace Config File
$config = file_get_contents( "config.php" );
$config = str_replace( "{DB}", $database_name, $config );
$config = str_replace( "{HOST}", "localhost", $config );
$config = str_replace( "{USER}", $user, $config );
$config = str_replace( "{PASS}", $password, $config );
file_put_contents( "config.php", $config );

do
{
    echo "[+] Please write your desired Admin Password(Minimum: 5 chars): ";
    fscanf( STDIN, '%s', $admin_password );
    $admin_password = trim( $admin_password );
} while ( strlen( $admin_password ) < 5 );


$host = gethostname();
$ip = gethostbyname( $host );
$SITE_URL = "http://$ip/";


echo "[+] Installing MySQL Tables...\n";

$con = mysql_connect( "localhost", "root", $mysql_root_pass );
mysql_select_db( $database_name );

#replace sql multics
$sql_file = str_replace( array(
    "{ADMIN_PASSWORD}",
    "{NOW_TIME}",
    "{SITE_URL}" ), array(
    md5( $admin_password ),
    time(),
    $SITE_URL ), file_get_contents( "db.sql" ) );


$file_content = explode( "\n", $sql_file );
$query = "";
foreach ( $file_content as $sql_line )
{
    if ( trim( $sql_line ) != "" && strpos( $sql_line, "--" ) === false )
    {
        $query .= $sql_line;
        if ( substr( rtrim( $query ), -1 ) == ';' )
        {
            mysql_query( $query );
            $query = "";
        }
    }
}

mysql_close( $con );

system( "rm -rf index.html > /dev/null 2>&1" );
system( "rm -rf db.sql > /dev/null 2>&1" );
system( "rm -rf multics_panel.zip > /dev/null 2>&1" );

system( "chmod -Rf 0777 . /dev/null 2>&1" );


echo "[+] Restarting Services...\n";
system( "service apache2 restart > /dev/null 2>&1" );
system( "service mysql restart > /dev/null 2>&1" );


echo "\n\n All Done\n\nHost(Not 100%): $SITE_URL\nYour Admin Username is: admin\nYour Admin Password is: $admin_password\n\nThank you for using MultiCS Panel\n\n";


function GenerateString( $length = 10 )
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    $str = '';
    $max = strlen( $chars ) - 1;

    for ( $i = 0; $i < $length; $i++ )
        $str .= $chars[rand( 0, $max )];

    return $str;
}

?>