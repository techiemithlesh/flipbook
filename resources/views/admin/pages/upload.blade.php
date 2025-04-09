@extends('admin.layout.index')

@section('title')
    <title>Upload Flipbook</title>
@endsection

@section('styles')
    <link href="https://releases.transloadit.com/uppy/v3.3.1/uppy.min.css" rel="stylesheet">
@endsection

@section('content')
    <h1 class="h3 mb-4 text-gray-800">Upload New Flipbook</h1>

    <div class="row">
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Upload Flipbook</h6>
                </div>
                <div class="card-body">
                    <div id="drag-drop-area"></div>
                    <div class="mt-3" id="upload-progress"></div>
                    <div class="mt-3" id="response-message"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const uppy = new Uppy.Uppy({
                debug: true,
                autoProceed: false,
                restrictions: {
                    maxNumberOfFiles: 1,
                    allowedFileTypes: ['.zip']
                }
            });

            uppy.use(Uppy.Dashboard, {
                inline: true,
                target: '#drag-drop-area',
                showProgressDetails: true,
                height: 300,
                proudlyDisplayPoweredByUppy: false
            });

            // Use XHRUpload for direct presigned URL uploads
            uppy.use(Uppy.XHRUpload, {
                method: 'PUT',
                formData: false,
                bundle: false,
                allowedMetaFields: [],
            });

            // Add a custom logging panel to help debug issues
            const logElement = document.createElement('div');
            logElement.id = 'debug-log';
            logElement.style.maxHeight = '200px';
            logElement.style.overflow = 'auto';
            logElement.style.fontSize = '12px';
            logElement.style.fontFamily = 'monospace';
            logElement.style.padding = '8px';
            logElement.style.backgroundColor = '#f5f5f5';
            logElement.style.marginTop = '10px';
            logElement.style.display = 'none'; // Hide by default
            document.getElementById('drag-drop-area').parentNode.appendChild(logElement);

            // Add a toggle button for debug logs
            const toggleButton = document.createElement('button');
            toggleButton.textContent = 'Show Debug Logs';
            toggleButton.className = 'btn btn-sm btn-secondary mt-2';
            toggleButton.onclick = function() {
                const logPanel = document.getElementById('debug-log');
                if (logPanel.style.display === 'none') {
                    logPanel.style.display = 'block';
                    this.textContent = 'Hide Debug Logs';
                } else {
                    logPanel.style.display = 'none';
                    this.textContent = 'Show Debug Logs';
                }
            };
            document.getElementById('drag-drop-area').parentNode.appendChild(toggleButton);

            // Helper function to log messages with timestamp
            function logDebug(message, data = null) {
                const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
                const logItem = document.createElement('div');
                logItem.textContent = `[${timestamp}] ${message}`;

                if (data) {
                    console.log(message, data);
                    if (typeof data === 'object') {
                        const pre = document.createElement('pre');
                        pre.textContent = JSON.stringify(data, null, 2);
                        pre.style.fontSize = '10px';
                        pre.style.margin = '4px 0';
                        logItem.appendChild(pre);
                    } else {
                        logItem.textContent += `: ${data}`;
                    }
                }

                document.getElementById('debug-log').prepend(logItem);
            }

            // Handle file upload preparation
            uppy.on('file-added', async (file) => {
                logDebug('File added', {
                    name: file.name,
                    size: file.size,
                    type: file.type
                });

                try {
                    logDebug('Requesting presigned URL...');
                    const response = await fetch("{{ url('/s3-presign') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            filename: file.name,
                            filetype: file.type || 'application/zip'
                        })
                    });

                    if (!response.ok) {
                        const errorText = await response.text();
                        logDebug('Presigned URL request failed', {
                            status: response.status,
                            statusText: response.statusText,
                            response: errorText
                        });
                        throw new Error(
                            `Failed to get presigned URL: ${response.status} ${response.statusText}`
                        );
                    }

                    const data = await response.json();
                    logDebug('Received presigned URL data', data);

                    if (data.error) {
                        throw new Error(data.error);
                    }

                    // Store upload data in file meta
                    uppy.setFileMeta(file.id, {
                        endpoint: data.url,
                        method: data.method || 'PUT',
                        headers: data.headers || {},
                        publicUrl: data.publicUrl,
                        path: data.path
                    });

                    logDebug('File meta updated with presigned URL data');
                } catch (error) {
                    logDebug('Error preparing upload', error.message);
                    uppy.info({
                        message: `Error: ${error.message}`,
                        type: 'error',
                        duration: 10000
                    });
                }
            });

            // Configure upload just before it starts
            uppy.on('upload', (data) => {
                logDebug('Upload started', {
                    fileCount: data.fileIDs.length
                });

                data.fileIDs.forEach(fileID => {
                    const file = uppy.getFile(fileID);
                    const meta = file.meta;

                    if (!meta.endpoint) {
                        logDebug('No endpoint for file', fileID);
                        uppy.info({
                            message: 'No upload URL available for this file',
                            type: 'error',
                            duration: 5000
                        });
                        return;
                    }

                    // Set the endpoint for XHRUpload
                    logDebug('Setting upload endpoint', meta.endpoint);
                    uppy.getPlugin('XHRUpload').setOptions({
                        endpoint: meta.endpoint,
                        method: meta.method,
                        headers: meta.headers
                    });
                });
            });

            // Display upload progress
            uppy.on('upload-progress', (file, progress) => {
                const percent = Math.round((progress.bytesUploaded / progress.bytesTotal) * 100);
                logDebug(`Upload progress: ${percent}%`, {
                    fileID: file.id
                });

                document.getElementById('upload-progress').innerHTML =
                    `<div class="progress">
                <div class="progress-bar" role="progressbar" style="width: ${percent}%" 
                    aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100">${percent}%</div>
                </div>`;
            });

            // Handle upload errors
            uppy.on('upload-error', (file, error, response) => {
                logDebug('Upload error', {
                    error: error.message,
                    response
                });
                document.getElementById('response-message').innerHTML =
                    `<div class="alert alert-danger">Upload failed: ${error.message}</div>`;
            });

            // Handle successful uploads
            uppy.on('complete', async (result) => {
                logDebug('Upload complete', {
                    successful: result.successful.length,
                    failed: result.failed.length
                });

                if (result.successful.length === 0) {
                    return;
                }

                const uploadedFile = result.successful[0];
                const publicUrl = uploadedFile.meta.publicUrl || uploadedFile.uploadURL || uploadedFile
                    .meta.path || '';

                try {
                    logDebug('Storing metadata...');
                    const metadataResponse = await fetch(
                        "{{ route('admin.flipbooks.store-metadata') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({
                                name: (uploadedFile.meta.name || uploadedFile.name ||
                                    'Flipbook').replace('.zip', ''),
                                url: publicUrl
                            })
                        });

                    if (!metadataResponse.ok) {
                        const errorText = await metadataResponse.text();
                        logDebug('Metadata storage failed', {
                            status: metadataResponse.status,
                            response: errorText
                        });
                        throw new Error('Failed to save metadata');
                    }

                    const metadataData = await metadataResponse.json();
                    logDebug('Metadata stored successfully', metadataData);

                    document.getElementById('response-message').innerHTML =
                        `<div class="alert alert-success">
                ${metadataData.message}
                <a href="/admin/flipbooks/${metadataData.book.slug}" class="btn btn-sm btn-primary mt-2">
                    View Flipbook
                </a>
            </div>`;
                } catch (error) {
                    logDebug('Metadata error', error.message);
                    document.getElementById('response-message').innerHTML =
                        `<div class="alert alert-warning">
                File uploaded successfully, but failed to save metadata: ${error.message}
            </div>`;
                }
            });

        });
    </script>
@endpush
