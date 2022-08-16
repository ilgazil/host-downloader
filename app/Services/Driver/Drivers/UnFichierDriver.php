<?php

namespace App\Services\Driver\Drivers;

use anlutro\cURL\cURL;
use App\Exceptions\DriverExceptions\AuthException;
use App\Exceptions\DriverExceptions\DriverException;
use App\Exceptions\FileExceptions\DownloadCooldownException;
use App\Exceptions\FileExceptions\DownloadException;
use App\Models\Driver as DriverModel;
use App\Services\Driver\DriverInterface;
use App\Services\File\Download;
use App\Services\File\Metadata;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\ContentLengthException;
use PHPHtmlParser\Exceptions\LogicalException;
use PHPHtmlParser\Exceptions\StrictException;

class UnFichierDriver extends DriverInterface
{
    public function match(string $url): bool {
        return (bool) preg_match('/https?:\/\/1fichier\.com\/\?[\w\d]+/', $url);
    }

    public function getName(): string
    {
        return 'UnFichier';
    }

    /**
     * @throws AuthException
     */
    public function authenticate(string $login, string $password): void
    {
        throw new AuthException('Not implemented');
    }

    public function unauthenticate(): void
    {
        DriverModel::find($this->getName())?->delete();
    }

    /**
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ContentLengthException
     * @throws DriverException
     * @throws LogicalException
     * @throws StrictException
     */
    public function getMetadata(string $url): Metadata
    {
        $parser = new UnFichierParser($this->getDom($url));

        $metadata = new Metadata();
        $metadata->setDriverName($this->getName());
        $metadata->setFileName($parser->getFileName());
        $metadata->setFileSize($parser->getFileSize());
        $metadata->setFileError($parser->getFileError());

        return $metadata;
    }

    /**
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ContentLengthException
     * @throws DriverException
     * @throws DownloadCooldownException
     * @throws DownloadException
     * @throws LogicalException
     * @throws StrictException
     */
    public function getDownload(string $url): Download
    {
        $parser = new UnFichierParser($this->getDom($url));

        if ($parser->getFileError()) {
            throw new DownloadException($parser->getFileError());
        }

        $download = new Download();
        $download->setDriver($this);
        $download->setFileName($parser->getFileName());
        $download->setFileSize($parser->getFileSize());

        if ($parser->getAnonymousDownloadToken()) {
            $parser = $this->postAnonymousDownloadToken($url, $parser->getAnonymousDownloadToken());
        }

        $downloadLink = $parser->getAnonymousDownloadLink();

        if (!$downloadLink) {
            throw new DownloadException('Unable to get download link');
        }

        $download->setUrl($downloadLink);

        return $download;
    }

    /**
     * @throws DriverException
     */
    protected function validateUrl(string $url): void
    {
        if (!$this->match($url)) {
            throw new DriverException('Wrong host for querying info : ' . $this->getName() . ' cannot handle ' . $url);
        }
    }

    /**
     * @throws DriverException
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws StrictException
     */
    protected function getDom(string $url): Dom
    {
        $this->validateUrl($url);

        $response = (new cURL())->newRequest('get', $url)->send();

        if ($response->statusCode !== 200) {
            throw new DriverException('Unable to reach ' . $url . ' (received ' . $response->statusText . ')');
        }

        $dom = new Dom();
        $dom->loadStr($response->body);

        return $dom;
    }

    /**
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ContentLengthException
     * @throws StrictException
     * @throws LogicalException
     */
    protected function postAnonymousDownloadToken(string $url, string $token): UnFichierParser
    {
        $curl = new cURL();

        $response = $curl->post($url, ['adz' => $token]);

        $dom = new Dom();
        $dom->loadStr($response->body);

        return new UnFichierParser($dom);
    }
}
