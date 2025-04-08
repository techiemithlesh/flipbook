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
                    <form id="upload-form" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label for="file">Flipbook ZIP File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control-file" name="file" id="file" required>
                            <small class="form-text text-muted">Upload a .zip file (must contain index.html inside each folder).</small>
                        </div>

                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="is_bulk" name="is_bulk">
                            <label class="form-check-label" for="is_bulk">This ZIP contains multiple flipbooks</label>
                        </div>

                        <button type="submit" class="btn btn-primary">Upload Flipbook</button>

                        <div class="progress mt-3" style="height: 20px; display: none;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                role="progressbar" style="width: 0%">0%</div>
                        </div>

                        <div class="mt-3" id="response-message"></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        $('#upload-form').on('submit', function (e) {
            e.preventDefault();

            let formData = new FormData(this);
            let progressBar = $('.progress');
            let progressValue = $('.progress-bar');
            let responseMsg = $('#response-message');

            progressBar.show();
            progressValue.css('width', '0%').text('0%');
            responseMsg.html('');

            $.ajax({
                xhr: function () {
                    let xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function (evt) {
                        if (evt.lengthComputable) {
                            let percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            progressValue.css('width', percentComplete + '%').text(percentComplete + '%');
                        }
                    }, false);
                    return xhr;
                },
                url: "{{ route('admin.flipbooks.upload') }}",
                type: "POST",
                data: formData,
                contentType: false,
                processData: false,
                success: function (response) {
                    responseMsg.html('<div class="alert alert-success">' + response.message + '</div>');
                    progressValue.css('width', '100%').text('100%');
                    $('#upload-form')[0].reset();
                },
                error: function (xhr) {
                    responseMsg.html('<div class="alert alert-danger">Error: ' + (xhr.responseJSON?.message ?? 'Upload failed') + '</div>');
                    progressBar.hide();
                }
            });
        });
    });
</script>
@endpush
