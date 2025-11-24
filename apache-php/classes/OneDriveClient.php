<?php
namespace App;
use RuntimeException;
use SimpleXMLElement;

class OneDriveClient
{
    private string $baseUrl;

    /**
     * Zapisanie pliku na Onedrive
     * @param string|null $baseUrl - adres kontenera WebDAV, domyślnie z ENVa jeśli nie jest podany
     */
    public function __construct(?string $baseUrl = NULL)
    {
        if ($baseUrl !== NULL) {
            $this->baseUrl = rtrim($baseUrl, '/') . '/';
        } elseif (!empty($_ENV['WEBDAV_URL'])) {
            $this->baseUrl = $_ENV['WEBDAV_URL'];
        } else {
            throw new RuntimeException("Brak adresu kontenera WebDAV w konstruktorze.");
        }
    }

    /**
     * Stworzenie katalogu na Onedrive
     * @param string $directory - ścieżka zdalna na Onedrive + nazwa folderu
     * @return array ['success' => bool, 'error' => string]
     */
    public function mkdir(string $directory): array
    {
        $url = $this->baseUrl . rawurlencode($directory);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'MKCOL',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 201) {
            return ['success' => true, 'error' => ''];
        }else {
            return ['success' => false, 'error' => 'Błąd: ' . $httpCode . ' ' . $curlErr];
        }
    }

    /**
     * Zapisanie pliku na Onedrive
     * @param string $localFile - ściezka lokalna do pliku
     * @param string $directory - ścieżka zdalna na Onedrive
     * @param string $fileName - nazwa pliku
     * @return array ['success' => bool, 'error' => string]
     */
    public function saveFile(string $localFile, string $directory, string $fileName): array
    {
        $parts = array_map('rawurlencode', explode('/', trim($directory, '/')));
        $path = implode('/', $parts);
        if (!empty($path)) $path .= '/';
        $path .= rawurlencode($fileName);

        $url = rtrim($this->baseUrl, '/') . '/' . $path;

        $size = filesize($localFile);
        $fp = fopen($localFile, 'r');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_PUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => $size,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 300,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);

        fclose($fp);
        curl_close($ch);

        $success = $httpCode >= 200 && $httpCode < 300;
        $error = $success ? '' : ($curlErr ?: 'Błąd HTTP ' . $httpCode);

        return ['success' => $success, 'error' => $error];
    }
    /**
     * Usunięcie pliku z OneDrive
     * @param string $path - ścieżka do pliku
     * @return array ['success' => bool, 'error' => string]
     */
    public function deleteFile(string $path): array
    {
        $path = rawurlencode($path);
        $url = $this->baseUrl . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'error' => ''];
        } elseif ($httpCode === 404) {
            return ['success' => false, 'error' => 'Plik nie istnieje.'];
        } else {
            return ['success' => false, 'error' => 'Błąd: ' . $httpCode . ' ' . $curlErr];
        }
    }
    /**
     * Pobranie drzewa katalogów (tylko katalogi) z OneDrive
     *
     * @param string $directory - katalog startowy
     * @param string $depth - jak głęboko pobierać podkatalogi (1 = tylko bieżący katalog, infinity = wszystkie podkatalogi)
     * @return array ['success' => bool, 'error' => string, 'directories' => array]
     */
    public function listDirectories(string $directory, string $depth): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . rawurlencode($directory);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Depth: ' . $depth
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            return [
                'success' => false,
                'error' => "Błąd HTTP: $httpCode $curlErr",
                'directories' => []
            ];
        }

        try {
            $xml = new SimpleXMLElement($response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "Nie udało się sparsować XML: " . $e->getMessage(),
                'directories' => []
            ];
        }

        $tree = [];

        foreach ($xml->xpath('//*[local-name()="response"]') as $resp) {
            $hrefArr = $resp->xpath('*[local-name()="href"]');
            if ($hrefArr === false || count($hrefArr) === 0) continue;

            $href = (string)$hrefArr[0];
            $href = urldecode(trim($href, '/'));

            if ($href === rtrim($directory, '/')) continue;

            $relativePath = ltrim(str_replace(rtrim($directory, '/'), '', $href), '/');
            $parts = explode('/', $relativePath);

            $current =& $tree;
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    if (str_contains($part, '.')) {
                        $current[$part] = NULL;
                    } else {
                        if (!isset($current[$part])) {
                            $current[$part] = [];
                        }
                    }
                } else {
                    if (!isset($current[$part])) {
                        $current[$part] = [];
                    }
                    $current =& $current[$part];
                }
            }
        }

        return [
            'success' => true,
            'directories' => $tree,
        ];
    }
    public function directoryExists(string $directory): bool
    {
        $url = rtrim($this->baseUrl, '/') . '/' . rawurlencode($directory);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Depth: 0'
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode === 207) {
            return true;
        }
        return false;
    }


}
