<?php

namespace luiseduardo\correios;

use DOMDocument;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;


class CepAction extends \yii\base\Action

{
    const URL_VIACEP = 'https://viacep.com.br/ws/';

    /**
     * @var string name of query parameter
     */
    public $queryParam = '_cep';

    /**
     * Searches address by cep or location
     * @return array cep data
     * @throws NotFoundHttpException
     */
    public function run()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $query = Yii::$app->request->get($this->queryParam);
        $result = $this->search($query);

        if (!$result) {
            throw new NotFoundHttpException("Endereço não encontrado");
        }

        return $result;
    }

    /**
     * Processes html content, returning cep data
     * @param string $q query
     * @return array cep data
     */
    protected function search($q)
    {
        $result = [];

        // somente números, vamos pesquisar por cep
        if (preg_match('/^\d+$/', $q)) {
            $url = "{$q}/json/";
        } else {
            // vamos pesquisar por endereço

            $q = str_replace('RUA', '', mb_strtoupper($q));

            // Quebrar em partes
            $partes = explode(',', $q);

            // Remover espaços e codificar cada parte
            $partesLimpas = array_map(function ($parte) {
                return urlencode(trim($parte));
            }, $partes);

            $endereco = $partesLimpas[0];
            $cidade = $partesLimpas[1];
            $estado = $partesLimpas[2];

            $url = "$estado/$cidade/" . urlencode($endereco) . '/json';
        }

        $curl = curl_init(self::URL_VIACEP . $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);

        $response = curl_exec($curl);
        curl_close($curl);

        $result = json_decode($response);

        if (is_array($result)) {

            foreach ($result as $address) {
                $output[] = [
                    'location' => ($address->logradouro ?? $endereco ?? ''),
                    'district' => $address->bairro,
                    'city' => $address->localidade,
                    'state' => $address->uf,
                    'cep' => $address->cep,
                    'complement' => $address->complemento
                ];
            }
        } else {
            $address = $result;
            $output[] = [
                'location' => ($address->logradouro ?? $endereco ?? ''),
                'district' => $address->bairro,
                'city' => $address->localidade,
                'state' => $address->uf,
                'cep' => $address->cep,
                'complement' => $address->complemento
            ];
        }

        return $output;
    }
}
