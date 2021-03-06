<?php

//not done yet!

class BoMapProjection 
{
	var $Method;
	var $ImageWidth;
	var $ImageHeight;
	var $ImageCalibrationX;
	var $ImageCalibrationY;
	var $ImageOffsetX;
	var $ImageOffsetY;
	var $ImageCoor;
	var $SatLon;
	var $StrikeBounds = array();
	var $LonRef;
	
	public $UseSql = false;
	
	function __construct($proj = '', $w, $h, $coord)
	{
		$this->ImageWidth  = $w;
		$this->ImageHeight = $h;
		$this->Method      = $proj;
		$this->ImageCoor   = $coord;
		
		//calibration points in image (default: top/right and bottom/left <-> N/E and S/W points)
		$cN = isset($coord[4]) ? $coord[4] : 0;
		$cE = isset($coord[5]) ? $coord[5] : $this->ImageWidth;
		$cS = isset($coord[6]) ? $coord[6] : $this->ImageHeight;
		$cW = isset($coord[7]) ? $coord[7] : 0;

		if (isset($coord[8]))
			$this->SatLon = $coord[8];

		
		//Longitude Reference point (middle for most projections)
		$this->LonRef = $coord[3] + ($coord[1] - $coord[3]) / 2;

		//Image Dimensions
		list($x1, $y1) = $this->Calculate($coord[2], $coord[3]); //South, West
		list($x2, $y2) = $this->Calculate($coord[0], $coord[1]); //North, East
	
		$this->ImageCalibrationX = ($cE - $cW) / ($x2 - $x1);
		$this->ImageCalibrationY = ($cS - $cN) / ($y2 - $y1);
		
		$this->ImageOffsetX = $x1 * $this->ImageCalibrationX - $cW;
		$this->ImageOffsetY = $y1 * $this->ImageCalibrationY + $cS;

		
		//Strike bounds
		if (isset($coord[9]) && isset($coord[10]) && isset($coord[11]) && isset($coord[12]))
		{
			$this->StrikeBounds[0] = $coord[9];
			$this->StrikeBounds[1] = $coord[10];
			$this->StrikeBounds[2] = $coord[11];
			$this->StrikeBounds[3] = $coord[12];
		}
		
	}
	
	function SetLonRef($lon)
	{
		$this->LonRef = $lon;
	}
	
	function SqlSelect($lat_name, $lon_name, $as_x = '', $as_y = '', $tablename = null)
	{
		switch ($this->Method)
		{
			default:
				$this->UseSql = true;
				
				if (BO_DB_STRIKES_MERCATOR === true && $tablename == 'strikes')
				{
					$lon_name .= '_merc';
					$lat_name .= '_merc';
					$scale = (1 << BO_DB_STRIKES_MERCATOR_SCALE) * 256;
					$lon_x = " ROUND(  (($lon_name / $scale) - 0.5) * $this->ImageCalibrationX - $this->ImageOffsetX ) ";
					$lat_y = " ROUND(  (($lat_name / $scale) - 0.5) * $this->ImageCalibrationY + $this->ImageOffsetY ) ";
				}
				else
				{
					$lon_x = " ROUND( $lon_name / 360 * $this->ImageCalibrationX - $this->ImageOffsetX ) ";
					$lat_y = " ROUND( -LOG(TAN( PI()/4 + RADIANS($lat_name)/2 )) / PI() / 2 * $this->ImageCalibrationY + $this->ImageOffsetY ) ";
				}
				
				$sql = " $lat_y $as_y, $lon_x $as_x ";
				break;
			
			case 'miller';
				$this->UseSql = true;
				$lon_x = " ROUND( $lon_name / 360 * $this->ImageCalibrationX - $this->ImageOffsetX ) ";
				$lat_y = " ROUND( -5/4*LOG(TAN( PI()/4 + RADIANS($lat_name)*2/5 )) / PI() / 2 * $this->ImageCalibrationY + $this->ImageOffsetY ) ";
				$sql = " $lat_y $as_y, $lon_x $as_x ";
				break;


			case 'plate':
			case 'geos':
				$sql = " $lat_name, $lon_name";
				break;
		}
		
		return $sql;
	}
	
	
	function LatLon2Image($lat, $lon, $no_sql = false)
	{
		$result = $this->Calculate($lat, $lon, $no_sql);
		
		if ($result === false)
		{
			return array(false, false);
		}
		
		list($px, $py) = $result;
		$x =  $px * $this->ImageCalibrationX - $this->ImageOffsetX;
		$y = -$py * $this->ImageCalibrationY + $this->ImageOffsetY;
		return array($x, $y);
	}

	
	function Calculate($lat, $lon, $no_sql = false)
	{
		if ($this->UseSql && !$no_sql)
		{
			return array($lon, $lat);
		}
		
		if ($lon < $this->LonRef-180)
			$lon = $lon+360;
		else if ($lon > $this->LonRef+180)
			$lon = $lon-360;
		
		switch ($this->Method)
		{

			default:
				return bo_latlon2mercator($lat, $lon);

			case 'miller':
				return bo_latlon2miller($lat, $lon);
				
			case 'plate':
				return array($lon, $lat);
			
			//Normalized Geostationary Projection
			//by EUMETSAT
			case 'geos':
				return bo_latlon2geos($lat, $lon, $this->SatLon);
		}
	}
	
	//ToDo: We need calculation image-pixel -> lat/lon for that (or extra cfg-setting)
	function GetBounds()
	{
		switch ($this->Method)
		{
			case 'geos':
				
				if (empty($this->StrikeBounds))
					$bounds = array(90, 180, -90, -180);
				else
					$bounds = $this->StrikeBounds;
			
				return $bounds;
				
			default:
				return array($this->ImageCoor[0], $this->ImageCoor[1], $this->ImageCoor[2], $this->ImageCoor[3]);
	
		}
		
	}
	
}


?>