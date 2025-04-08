@extends('admin.layout.index')

@section('title')
    <title>Flipbook List</title>
@endsection

@section('content')
    <h1 class="h3 mb-4 text-gray-800">All Flipbooks</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Slug</th>
                        <th>URL</th>
                        <th>Uploaded At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($books as $book)
                        <tr>
                            <td>{{ $book->title }}</td>
                            <td>{{ $book->slug }}</td>
                            <td>
                                <a href="<?=base_url('/{{ $book->slug }}/')  ?>" target="_blank">
                                    View Flipbook
                                </a>
                            </td>
                            <td>{{ $book->created_at->format('d M Y') }}</td>
                            <td>
                                Future: Edit/Delete
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">No flipbooks found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
