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
    <script src="https://releases.transloadit.com/uppy/v3.3.1/uppy.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const uppy = new Uppy.Uppy({
                restrictions: {
                    maxNumberOfFiles: 1,
                    allowedFileTypes: ['.zip']
                },
                autoProceed: true
            });

            uppy.use(Uppy.Dashboard, {
                inline: true,
                target: '#drag-drop-area',
                showProgressDetails: true,
                height: 300,
                proudlyDisplayPoweredByUppy: false
            });

            uppy.use(Uppy.XHRUpload, {
                endpoint: "{{ route('flipbooks.upload') }}",
                fieldName: 'flipbook',
                timeout: 5 * 60 * 1000,
                formData: true,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            });

            uppy.on('upload-progress', (file, progress) => {
                const percent = Math.round((progress.bytesUploaded / progress.bytesTotal) * 100);
                document.getElementById('upload-progress').innerHTML = `
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: ${percent}%" 
                            aria-valuenow="${percent}" aria-valuemin="0" aria-valuemax="100">
                            ${percent}%
                        </div>
                    </div>`;
            });

            uppy.on('complete', (result) => {
                const response = result.successful[0]?.response?.body;

                if (response?.publicUrl) {
                    document.getElementById('response-message').innerHTML = `
                        <div class="alert alert-success">
                            Flipbook uploaded successfully: 
                            <a href="${response.publicUrl}" target="_blank">${response.publicUrl}</a>
                        </div>`;
                } else {
                    document.getElementById('response-message').innerHTML = `
                        <div class="alert alert-warning">Upload completed, but no URL returned.</div>`;
                }
            });

            uppy.on('upload-error', (file, error) => {
                console.error('Upload error:', error);
                document.getElementById('response-message').innerHTML = `
                    <div class="alert alert-danger">Upload failed: ${error.message}</div>`;
            });
        });
    </script>
@endpush
