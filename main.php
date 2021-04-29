<?php

use Psr\Http\Message\ServerRequestInterface;
use Google\Cloud\Firestore\FirestoreClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

function main(ServerRequestInterface $request)
{
    $data = $request->getQueryParams();

    $return = new Response();

    if (empty($data['lat']) && empty($data['lng'])) {

        return $return->withStatus(400);

    }

    $firestore = new FirestoreClient();
    $collectionReference = $firestore->collection('RamenStores');

    $radiusKm = 5;
    $degreePerKmForLatitude  = 0.0090133729745762;       // 北緯35度における1kmあたりの緯度
    $degreePerKmForLongitude = 0.010966404715491394;    // 北緯35度における1kmあたりの経度
    $latitudeGreaterThan  = $data['lat'] - ($radiusKm * $degreePerKmForLatitude);
    $latitudeLessThan     = $data['lat'] + ($radiusKm * $degreePerKmForLatitude);
    $longitudeGreaterThan = $data['lng'] - ($radiusKm * $degreePerKmForLongitude);
    $longitudeLessThan    = $data['lng'] + ($radiusKm * $degreePerKmForLongitude);

    // 先にlatitudeだけで絞り込み
    $query = $collectionReference->where('locationLatitude', '>', $latitudeGreaterThan)
        ->where('locationLatitude', '<', $latitudeLessThan);

    $documents = $query->documents();

    $res_items = [];

    foreach ($documents as $document) {

        // Firestoreから取得したdocumentsの中から、longitudeが検索条件内に収まるアイテムを返却
        if ($document['locationLongitude'] > $longitudeGreaterThan && $document['locationLongitude'] < $longitudeLessThan) {
            $res_items[] = [
                'name' => $document['name'],
                'latitude' => $document['locationLatitude'],
                'longitude' => $document['locationLongitude']
            ];
        }

    }

    return $return->withHeader('Content-Type', 'application/json')->withBody(Utils::streamFor(json_encode($res_items)));

}
