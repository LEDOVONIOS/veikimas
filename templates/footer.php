        </div>
    </main>
    
    <!-- Footer -->
    <footer class="mt-5 py-3" style="background: var(--bg-secondary); border-top: 1px solid var(--border-color);">
        <div class="container-fluid text-center">
            <small style="color: var(--text-muted);">
                &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved. 
                | Powered by <a href="https://seorocket.lt" target="_blank">SEO Rocket</a>
            </small>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Initialize tooltips
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
        });
        
        // Auto-dismiss alerts after 5 seconds
        window.setTimeout(function() {
            $(".alert").fadeTo(500, 0).slideUp(500, function(){
                $(this).remove(); 
            });
        }, 5000);
        
        // Confirm delete actions
        $('.delete-confirm').on('click', function(e) {
            e.preventDefault();
            const href = $(this).attr('href');
            const title = $(this).data('title') || 'Are you sure?';
            const text = $(this).data('text') || 'This action cannot be undone!';
            
            Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        });
        
        // Format numbers with commas
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        // Update time ago strings
        function updateTimeAgo() {
            $('.time-ago').each(function() {
                const timestamp = $(this).data('timestamp');
                const timeAgo = moment(timestamp).fromNow();
                $(this).text(timeAgo);
            });
        }
        
        // Update time ago every minute
        setInterval(updateTimeAgo, 60000);
        updateTimeAgo();
        
        // Copy to clipboard
        $('.copy-to-clipboard').on('click', function() {
            const text = $(this).data('text');
            const temp = $('<input>');
            $('body').append(temp);
            temp.val(text).select();
            document.execCommand('copy');
            temp.remove();
            
            $(this).tooltip('hide')
                .attr('data-original-title', 'Copied!')
                .tooltip('show');
            
            setTimeout(() => {
                $(this).tooltip('hide')
                    .attr('data-original-title', 'Copy to clipboard');
            }, 2000);
        });
    </script>
    
    <?php if (isset($additionalJS)) echo $additionalJS; ?>
</body>
</html>