function custom_backorder_script() {
    if (is_product()) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('.single_add_to_cart_button').on('click', function(event) {
                    var isBackorder = $(this).data('backorder');
                    
                    if (isBackorder) {
                        event.preventDefault(); // Prevent the default action
                        
                        // Show the modal
                        $('body').append('<div id="backorder-modal" class="modal"><div class="modal-content"><span class="close">&times;</span><p>We apologize, but the item you ordered is currently on backorder due to high demand. You can still place an order, but please expect a delay in shipping. Thank you for your patience and understanding.</p></div></div>');

                        // Style the modal
                        $('.modal').css({
                            display: 'block',
                            position: 'fixed',
                            zIndex: '1',
                            paddingTop: '60px',
                            left: '0',
                            top: '0',
                            width: '100%',
                            height: '100%',
                            overflow: 'auto',
                            backgroundColor: 'rgb(0,0,0)',
                            backgroundColor: 'rgba(0,0,0,0.4)'
                        });
                        $('.modal-content').css({
                            backgroundColor: '#fefefe',
                            margin: '5% auto',
                            padding: '20px',
                            border: '1px solid #888',
							borderRadius: '6px',
                            width: '420px'
                        });
                        $('.close').css({
                            color: '#aaa',
                            float: 'right',
                            fontSize: '28px',
                            fontWeight: 'bold'
                        });

                        // Close the modal
                        $('.close').on('click', function() {
                            $('#backorder-modal').remove();
                        });
                        $(window).on('click', function(event) {
                            if ($(event.target).is('#backorder-modal')) {
                                $('#backorder-modal').remove();
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }
}
add_action('wp_footer', 'custom_backorder_script');
