<?php
namespace App\Repositories;
use Rinvex\Repository\Repositories\EloquentRepository;

use Log;
use App\Store;
use App\UserOpinion;

use App\Classes\LocationCluster;

class StoreRepository extends EloquentRepository
{
    protected $repositoryId = 'rinvex.repository.store';
    protected $model = 'App\Store';

    /**
     * 回傳小於半徑的店家
     * @param  $radius, $location, $storeTypes
     * @return cluster
     */
    public function getNearbyStore($radius, $location, $storeTypes, $is_cluster)
    {
    	$datas = Store::where(function($q) use ($storeTypes){
            foreach($storeTypes as $type){
                $q->orWhere('type', 'LIKE', "%{$type}%");
            }
        })
        ->get();
        foreach($datas as $key => $data ){
            $dis = $this->distance($location['lat'], $location['lng'], $data->lat, $data->lng, 'K') * 1000;
            $datas[$key]['dis'] = $dis;
            if($dis > $radius){
                unset($datas[$key]);
            }else{
                $opinion = UserOpinion::where('place_id', '=', $datas[$key]['place_id'])
                ->join('users', 'users.id', '=', 'user_opinions.user_id')
                ->select(['users.name', 'users.facebook_id', 'user_opinions.comment'])
                ->get();
                $datas[$key]['comments'] = $opinion;
            }
        }

        $group = $this->cluster($datas);

        return [
        	'data' => $datas,
        	'group' => $group
        ];
    }

    private function cluster($data)
    {
    	Log::info('start cluster');
		$cluster = new LocationCluster($data);
		$group = [
		 	'50' => $cluster->groupby(50),
		 	'100' => $cluster->groupby(100),
		 	'300' => $cluster->groupby(300),
		 	'1000' => $cluster->groupby(1000),
		];

    	return $group;
    }

    private function distance($lat1, $lon1, $lat2, $lon2, $unit) {

      $theta = $lon1 - $lon2;
      $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
      $dist = acos($dist);
      $dist = rad2deg($dist);
      $miles = $dist * 60 * 1.1515;
      $unit = strtoupper($unit);

      if ($unit == "K") {
        return ($miles * 1.609344);
      } else if ($unit == "N") {
          return ($miles * 0.8684);
        } else {
            return $miles;
          }
    }
}
