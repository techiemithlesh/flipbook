<!-- Bootstrap core JavaScript-->
<script src="{{asset('backend/vendor/jquery/jquery.min.js')}}"></script>
<script src="{{asset('backend/vendor/bootstrap/js/bootstrap.bundle.min.js')}}"></script>

<!-- Core plugin JavaScript-->
<script src="{{asset('backend/vendor/jquery-easing/jquery.easing.min.js')}}"></script>

<!-- Custom scripts for all pages-->
<script src="{{asset('backend/js/sb-admin-2.min.js')}}"></script>

<!-- Page level plugins -->

@if (Request::is('admin/dashboard'))
  <script src="{{asset('backend/js/demo/chart-area-demo.js')}}"></script>
  <script src="{{asset('backend/js/demo/chart-pie-demo.js')}}"></script>
@endif

<script src="{{asset('backend/vendor/chart.js/Chart.min.js')}}"></script>
<!-- Uppy JS -->
<script src="https://releases.transloadit.com/uppy/v3.13.0/uppy.min.js"></script>