<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Documents\ShowDocumentRequest;
use App\Http\Requests\Document\DownloadMultipleDocumentsRequest;
use App\Libraries\MultiDB;
use App\Models\Document;
use App\Utils\TempFile;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use ZipStream\Option\Archive;
use ZipStream\ZipStream;

class DocumentController extends Controller
{
    use MakesHash;

    /**
     * @return Factory|View
     */
    public function index()
    {
        return render('documents.index');
    }

    /**
     * @param ShowDocumentRequest $request
     * @param Document $document
     * @return Factory|View
     */
    public function show(ShowDocumentRequest $request, Document $document)
    {
        return render('documents.show', [
            'document' => $document,
        ]);
    }

    public function download(ShowDocumentRequest $request, Document $document)
    {
        return Storage::disk($document->disk)->download($document->url, $document->name);
    }

    public function publicDownload(string $document_hash)
    {
        MultiDB::documentFindAndSetDb($document_hash);

        $document = Document::where('hash', $document_hash)->firstOrFail();

        $headers = [];

        if(request()->input('inline') == 'true') 
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);

        return Storage::disk($document->disk)->download($document->url, $document->name, $headers);
    }

    public function downloadMultiple(DownloadMultipleDocumentsRequest $request)
    {
        $documents = Document::whereIn('id', $this->transformKeys($request->file_hash))
            ->where('company_id', auth('contact')->user()->company->id)
            ->get();

        $documents->map(function ($document) {
            if (auth()->guard('contact')->user()->client->id != $document->documentable->id) {
                abort(401, 'Permission denied');
            }
        });

        $options = new Archive();

        $options->setSendHttpHeaders(true);

        $zip = new ZipStream(now() . '-documents.zip', $options);

        foreach ($documents as $document) {
            $zip->addFileFromPath(basename($document->diskPath()), TempFile::path($document->filePath()));
        }

        $zip->finish();
    }
}
