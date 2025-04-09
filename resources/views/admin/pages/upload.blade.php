@extends('admin.layout.index')

@section('title')
    <title>Upload Flipbook</title>
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
                    <div class="mt-3" id="response-message"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')




<script>
    const uppy = new Uppy.Uppy({
        restrictions: {
            maxNumberOfFiles: 1,
            allowedFileTypes: ['.zip']
        }
    });

    uppy.use(Uppy.Dashboard, {
        inline: true,
        target: '#drag-drop-area'
    });

    uppy.use(Uppy.AwsS3, {
        async getUploadParameters(file) {
            const response = await fetch("{{ url('/s3-presign') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    filename: file.name,
                    filetype: file.type
                })
            });

            const data = await response.json();

            return {
                method: 'PUT',
                url: data.url,
                headers: {
                    'Content-Type': file.type
                }
            };
        }
    });

    uppy.on('complete', (result) => {
        const uploadedFile = result.successful[0];
        const fileUrl = uploadedFile.uploadURL;

        fetch("{{ route('admin.flipbooks.store-metadata') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                name: uploadedFile.name,
                url: fileUrl
            })
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('response-message').innerHTML = `<div class="alert alert-success">${data.message}</div>`;
        })
        .catch(err => {
            document.getElementById('response-message').innerHTML = `<div class="alert alert-danger">Failed to save metadata.</div>`;
        });
    });
</script>
@endpush
 