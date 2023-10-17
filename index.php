<?php

// Using Tracker Adress: -> "http://torrent.tracker.durukanbal.com/"

	ini_set ( 'display_errors', FALSE );
	ini_set ( 'display_startup_errors', FALSE );
	// ini_set ( 'display_errors', TRUE );
	// ini_set ( 'display_startup_errors', TRUE );
	// error_reporting ( E_ALL );
	
	header ( "content-type: text/plain" );
	
	
	global $db;
	$db = new PDO ( 'mysql:host=localhost;dbname=DataBaseName', 'UserName', 'Password' );


	global $info_hash;
	$info_hash = urldecode ( $_GET['info_hash'] ?? '' );
	$info_hash = bin2hexsafe ( $info_hash );
	
	global $peer_id;
  $peer_id = urldecode ( $_GET['peer_id'] ?? '' );
	$peer_id = bin2hexsafe ( $peer_id );
	
	global $ip;
  $ip = $_SERVER['REMOTE_ADDR'];
	
	global $port;
  $port = $_GET['port'] ?? 0;
	
	global $uploaded;
  $uploaded = $_GET['uploaded'] ?? 0;
	
	global $downloaded;
  $downloaded = $_GET['downloaded'] ?? 0;
	
	global $left;
  $left = $_GET['left'] ?? 0;
	
	
    
    if 
	(
		isset ( $info_hash ) === TRUE 
		AND 
		isset ( $peer_id ) === TRUE 
		AND 
		isset ( $port ) === TRUE	
	) 
	{
		if 
		( 
			strlen ( $info_hash ) >= 1
			AND
			strlen ( $peer_id ) >= 1
			AND
			intval ( $port ) >= 0 AND intval ( $port ) <= 65535
		)
		{
			RemoveOldData();
			echo QueryRecored();
		}
		else
		{
			echo "Bad Request !";
		}
    }
    else
    {
    	echo "Bad Request !";
    }

    function bencode ( $data ) 
    {
        if ( is_string ( $data ) ) 
        {
            return strlen ( $data ) . ':' . $data;
        } 
        else if ( is_int ( $data ) ) 
        {
            return 'i' . $data . 'e';
        } 
        else if ( is_array ( $data ) ) 
        {
            if ( array_values ( $data ) === $data ) 
            {
                return 'l' . implode ( '', array_map ( 'bencode', $data ) ) . 'e';
            } 
            else 
            {
                $encoded_elements = array();
                foreach ( $data as $key => $value ) 
                {
                    $encoded_elements[] = bencode ( $key );
                    $encoded_elements[] = bencode ( $value );
                }
                return 'd' . implode ( '', $encoded_elements ) . 'e';
            }
        }
    
        return NULL;
    } // Function bencode
	
	function bin2hexsafe ( $hexString ) 
	{
		if ( ctype_xdigit ( $hexString ) ) 
		{
			return $hexString;
		} 
		else 
		{
			return bin2hex ( $hexString );
		}
	}
	
	function Response ( $info_hash )
	{
		global $db;
		
		$query = 
		'
			SELECT 
				peer_id, 
				ip, 
				port 
			FROM 
				peers 
			WHERE 
				info_hash = ?
		';
		$stmt = $db->prepare ( $query );
		$stmt->bindParam ( 1, $info_hash, PDO::PARAM_STR );
		$stmt->execute();
		$peers = $stmt->fetchAll ( PDO::FETCH_ASSOC );

		$response = array
		(
			'interval' => 1800,
			// 'min interval' => 900,  // Ekledim
			// 'complete' => 0,        // Ekledim
			// 'incomplete' => 2,      // Ekledim
			// 'peers' => array ()
			'peers' => array_map 
			( 
				function ( $peer )
				{
					return array
					(
						'peer id' => hex2bin ( $peer['peer_id'] ),
						'ip' => $peer['ip'],
						'port' => intval ( $peer['port'] )
					);
				}, 
				$peers 
			)
		);
		
		/*
		foreach ( $peers AS $index => $data )
		{
			array_push 
			( 
				$response["peers"], array
				(
					'peer id' => hex2bin ( $data['peer_id'] ),
					'ip' => $data['ip'],
					'port' => intval ( $data['port'] )
				)
			);
		}
		*/
		
		return bencode ( $response );
	}
	
	function RemoveOldData ()
	{
	  global $db;

		$query = $db->query 
		( 
			"
				SELECT NOW() AS 'current_time'
			" 
		);
		$result = $query->fetch ( PDO::FETCH_ASSOC );
		$dbTime = new DateTime ( $result['current_time'] );
		$dbTime->format ( 'Y-m-d H:i:s' );
		
		$timeout = clone $dbTime;
        // $timeout = new DateTime();
        $timeout->modify ( '-1 hours' ); // 7 + 2 = 9 // 7 sabit
        
        $query = 
        '
            DELETE FROM 
                peers 
            WHERE 
                updated_at < ?
        ';
        $stmt = $db->prepare ( $query );
        $stmt->execute
        ( 
            array
            (
                $timeout->format ( 'Y-m-d H:i:s' )
            )
        );
		
	} // Function RemoveOldData
	
	function QueryRecored ()
	{
		global $db;
		global $info_hash;
		global $peer_id;
		global $ip;
		global $port;
		global $uploaded;
		global $downloaded;
		global $left;
		
        $query = 
		'
			INSERT INTO peers 
			(
				info_hash, 
				peer_id, 
				ip, 
				port, 
				uploaded, 
				downloaded, 
				remaining
			) 
			VALUES 
			(
				:info_hash, 
				:peer_id, 
				:ip1, 
				:port1, 
				:uploaded1, 
				:downloaded1, 
				:left1
			) ON DUPLICATE KEY UPDATE 
				ip = :ip2,
				port = :port2,
				uploaded = :uploaded2,
				downloaded = :downloaded2,
				remaining = :left2
		';

		$stmt = $db->prepare ( $query );

		// Insert
		$stmt->bindParam ( ':info_hash', $info_hash, PDO::PARAM_STR );
		$stmt->bindParam ( ':peer_id', $peer_id, PDO::PARAM_STR );
		$stmt->bindParam ( ':ip1', $ip, PDO::PARAM_STR );
		$stmt->bindParam ( ':port1', $port, PDO::PARAM_INT );
		$stmt->bindParam ( ':uploaded1', $uploaded, PDO::PARAM_INT );
		$stmt->bindParam ( ':downloaded1', $downloaded, PDO::PARAM_INT );
		$stmt->bindParam ( ':left1', $left, PDO::PARAM_INT );

		// Update
		$stmt->bindParam ( ':ip2', $ip, PDO::PARAM_STR );
		$stmt->bindParam ( ':port2', $port, PDO::PARAM_INT );
		$stmt->bindParam ( ':uploaded2', $uploaded, PDO::PARAM_INT );
		$stmt->bindParam ( ':downloaded2', $downloaded, PDO::PARAM_INT );
		$stmt->bindParam ( ':left2', $left, PDO::PARAM_INT );

		$stmt->execute();
		
		return Response ( $info_hash );
	}
?>
