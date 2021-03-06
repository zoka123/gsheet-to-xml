<?php

namespace Forikal\GsheetXml\Application\Service;

use Forikal\Library\GoogleAPI\GoogleAPIClient;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

class GoogleDriveFolderReadService
{
    const FOLDER_MIME_TYPE = 'application/vnd.google-apps.folder';
    const SPREADSHEET_MIME_TYPE = 'application/vnd.google-apps.spreadsheet';

    /** @var GoogleAPIClient */
    private $client;

    public function __construct(GoogleAPIClient $client)
    {
        $this->client = $client;
    }

    public function listSpreaadsheetsInFolder($folderId, bool $recursive)
    {
        /** @var Google_Service_Drive $service */
        $service = $this->client->driveService;

        /** @var Google_Service_Drive_DriveFile $file */
        $file = $service->files->get($folderId);

        // https://developers.google.com/drive/api/v3/folder
        // In the Drive API, a folder is essentially a file — one identified by the special
        // folder MIME type application/vnd.google-apps.folder
        $mimeType = $file->getMimeType();
        if (self::FOLDER_MIME_TYPE !== $mimeType) {
            throw new \Exception("File with ID $folderId is not Google Drive Folder");
        }

        // https://developers.google.com/drive/api/v3/search-parameters
        // https://github.com/google/google-api-php-client/issues/1285
        $files = $service->files->listFiles([
            'q' => "trashed = false AND '{$folderId}' IN parents ",
        ]);

        $fileIds = [];
        /** @var Google_Service_Drive_DriveFile $childrenFile */
        foreach ($files as $childrenFile) {

            if (self::FOLDER_MIME_TYPE === $childrenFile->getMimeType() && true === $recursive) {
                $subfolderSpreadsheets = $this->listSpreaadsheetsInFolder($childrenFile->getId(), true);
                $fileIds = array_merge($fileIds, $subfolderSpreadsheets);
                continue;
            }

            if (self::SPREADSHEET_MIME_TYPE !== $childrenFile->getMimeType()) {
                continue;
            }

            /**
             * If a file is called foo_, then it is assumed to be 'private' and should be explicitly ignored,
             * but it should be noted (in any feedback) that it was ignored
             *
             * Test if full file name ends with _ or only filename without the extension
             * i.e. foo_.xlsx and foo__
             */
            $fullName = $childrenFile->getName();
            $nameWithoutExtension = explode('.', $fullName)[0];
            if (
                '_' === substr($fullName, -1) ||
                '_' === substr($nameWithoutExtension, -1)
            ) {
                // @todo feedback that this file was ignored?
                continue;
            }

            $fileIds[] = $childrenFile->getId();
        }

        return $fileIds;
    }
}