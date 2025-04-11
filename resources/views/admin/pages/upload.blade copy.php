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

            
            // Handle file upload preparation
            uppy.on('file-added', async (file) => {
                
                try {
                    
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
                        
                        throw new Error(
                            `Failed to get presigned URL: ${response.status} ${response.statusText}`
                        );
                    }

                    const data = await response.json();

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
                } catch (error) {
                    
                    uppy.info({
                        message: `Error: ${error.message}`,
                        type: 'error',
                        duration: 10000
                    });
                }
            });

            // Configure upload just before it starts
            uppy.on('upload', (data) => {
                

                data.fileIDs.forEach(fileID => {
                    const file = uppy.getFile(fileID);
                    const meta = file.meta;

                    if (!meta.endpoint) {
                        
                        uppy.info({
                            message: 'No upload URL available for this file',
                            type: 'error',
                            duration: 5000
                        });
                        return;
                    }

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
                

                document.getElementById('upload-progress').innerHTML =
                    `<div class="progress">
                <div class="progress-bar" role="progressbar" style="width: ${percent}%" 
                    aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100">${percent}%</div>
                </div>`;
            });

            // Handle upload errors
            uppy.on('upload-error', (file, error, response) => {
                
                document.getElementById('response-message').innerHTML =
                    `<div class="alert alert-danger">Upload failed: ${error.message}</div>`;
            });

            // Handle successful uploads
            uppy.on('complete', async (result) => {
                
                if (result.successful.length === 0) {
                    return;
                }

                const uploadedFile = result.successful[0];
                const publicUrl = uploadedFile.meta.publicUrl || uploadedFile.uploadURL || uploadedFile
                    .meta.path || '';

                try {
                   
                    const metadataResponse = await fetch(
                        "{{ route('flipbooks.store-metadata') }}", {
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
                        
                        throw new Error('Failed to save metadata');
                    }

                    const metadataData = await metadataResponse.json();
                    

                    document.getElementById('response-message').innerHTML =
                        `<div class="alert alert-success">
                ${metadataData.message}
               
            </div>`;
                } catch (error) {
                    
                    document.getElementById('response-message').innerHTML =
                        `<div class="alert alert-warning">
                File uploaded successfully, but failed to save metadata: ${error.message}
            </div>`;
                }
            });

        });
    </script>
@endpush
