<?php
require( 'Curl.php' );

class TailToSlack{
    private function getNotifiers( $message ){
        if( strpos( $message, '] INFO ' ) ){
            return array();
        }

        return array( /*User @*/ );
    }

    private function ignoreMessage( $message ){
        if( empty( $message ) )
            return true;

        if( strpos( $message, 'stream_socket_client' ) )
            return true;

        return false;
    }

    private function formatMessage( $message ){
        static $host;
        if( !isset( $host ) ){
            $host = trim( `hostname` );
        }

        $notify = self::getNotifiers( $message );
        return "On `{$host}`". PHP_EOL . "```{$message}```" . PHP_EOL . implode( ' ', $notify );
    }

    private function postMessages( $messages ){
        static $data, $req;

        if( !isset( $data ) ){
            $data = array(
                "token"       => "xxxxxxxxxxxxxx",
                "channel"     => "yyyyyyyyyyyyyy",
                "link_names"  => 1
            );
        }

        if( !isset( $req ) ){
            // Endpoint - found in slack settings
            $req = curl_init("");
        }

        $data[ 'text' ] = implode( PHP_EOL, $messages );
        curl_setopt( $req, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt( $req, CURLOPT_POSTFIELDS, $data);
        curl_setopt( $req, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($req);
        curl_close($req);
    }

    public function tail( $path ){
        $fp = fopen( $path, 'r' );
        if( $fp === false )
            throw new Exception( "Can't open file for read: {$path}" );

        fseek( $fp, 0, SEEK_END );

        $prev = fstat( $fp );
        while( true ){
            $fstat = fstat( $fp );
            if( $fstat[ 'size' ] > $prev[ 'size' ] ){
                $buffer = '';
                $messages = array();
                while( ftell( $fp ) < $fstat[ 'size' ] ){
                    $line = fgets( $fp );
                    if( $line[0] == '[' ){
                        if( !self::ignoreMessage( $buffer ) ){
                            $messages[] = self::formatMessage( $buffer );
                        }

                        $buffer = $line;
                    }else{
                        $buffer .= $line;
                    }
                }

                if( !self::ignoreMessage( $buffer ) ){
                    $messages[] = self::formatMessage( $buffer );
                }

                if( !empty( $messages ) ){
                    self::postMessages( $messages );
                }
            }else{
                $doSleep = true;

                clearstatcache();
                if( file_exists( $path ) ){
                    $stat = stat( $path );
                    if( $fstat[ 'ino' ] !== $stat[ 'ino' ] ){
                        //echo "Inode changed, switching streams.";
                        fclose( $fp );

                        $fp = fopen( $path, 'r' );
                        if( $fp === false ){
                            throw new Exception( "Can't open file for read: {$path}" );
                        }else{
                            $prev[ 'size' ] = 0;
                        }

                        $doSleep = false;;
                    }
                }

                if( $doSleep ){
                    sleep( 5 );
                }
            }
            $prev = $fstat;
        }
    }
}

TailToSlack::tail( /* Log File to Tail */ );

//test inode swaps
//tail( '/var/log/test' );

//test notify all
//postMessage( 'test' );

//test notify Nic
//postMessage( '[xxx] PHP Notice test' );