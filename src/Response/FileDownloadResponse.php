<?php

namespace Zan\DoctrineRestBundle\Response;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * A response for sending data to the web browser that will be saved as a file
 *
 * This data can be from anywhere (such as a dynamically generated CSV file) and
 * does not need to be an existing file.
 */
class FileDownloadResponse extends Response
{
    /**
     * @param string $fileName      Filename the downloaded file will appear as to the user
     * @param string $mimeType      If null, it will be guessed from the file extension of $fileName
     * @param bool   $forceDownload If true, force the browser to download the file. If false, it may display inline
     */
    public function __construct(string $fileName, string $fileContents, $mimeType = null, bool $forceDownload = false)
    {
        // If null, attempt to guess the mime type
        if (null === $mimeType) {
            $mimeType = $this->guessMimeType(pathinfo($fileName, PATHINFO_EXTENSION));
        }

        $disposition = $forceDownload
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        $headers = [
            "Content-Disposition" => sprintf('%s; filename="%s"', $disposition, $fileName),
            "Content-Type" => $mimeType,
        ];

        parent::__construct(null, 200, $headers);

        $this->setContent($fileContents);
        $this->headers->set("Content-Length", strval(strlen($fileContents)));

        // Set a cookie in the response. This cookie is used for driving UIs
        // that need to display messages when a download has actually started.
        $cookie = new Cookie("zan_downloadPresented", strval(time()), 0, "/", null, false, false);
        $this->headers->setCookie($cookie);

        // Do not allow caching of this response
        $this->headers->addCacheControlDirective('no-store');
        $this->headers->addCacheControlDirective('private');
    }

    /**
     * Attempts to guess the mime type from a file extension:
     *
     * xls    - Microsoft Excel XLS file
     * xlsx   - Microsoft Excel 2007+ XLSX file
     * csv    - comma-separated values text file
     *
     * @see http://blogs.msdn.com/b/vsofficedeveloper/archive/2008/05/08/office-2007-open-xml-mime-types.aspx
     */
    protected function guessMimeType(string $extension): string
    {
        if ("xls" == $extension) {
            return "application/vnd.ms-excel";
        }
        if ("xlsx" == $extension) {
            return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
        }
        if ("csv" == $extension) {
            return "text/csv";
        }

        // Fall back to generic binary file
        return "application/octet-stream";
    }
}