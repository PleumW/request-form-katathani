    </main>

    <!-- Global Footer -->
    <footer class="bg-white border-t border-slate-200 mt-auto py-5 no-print text-slate-400 text-xs text-center">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-center space-x-2">
            <span class="font-medium text-slate-600">Katathani Collection</span>
            <span>&bull;</span>
            <span>Request Form</span>
        </div>
    </footer>

    <!-- SweetAlert2 CDN for modern dialogs -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Signature Pad Library CDN & Inline Fallback -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script>
        // Check if SignaturePad loaded from CDN, if not fallback to lightweight native canvas implementation
        if (typeof SignaturePad === 'undefined') {
            console.warn('SignaturePad CDN unavailable, initializing native canvas signature fallback...');
            window.SignaturePad = function(canvas) {
                var ctx = canvas.getContext('2d');
                var drawing = false;
                var isEmpty = true;

                function resize() {
                    var ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = canvas.offsetWidth * ratio;
                    canvas.height = canvas.offsetHeight * ratio;
                    ctx.scale(ratio, ratio);
                    ctx.lineWidth = 2.5;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    ctx.strokeStyle = '#0f172a';
                }
                resize();
                window.addEventListener('resize', resize);

                function getPos(e) {
                    var rect = canvas.getBoundingClientRect();
                    var clientX = e.clientX || (e.touches && e.touches[0].clientX);
                    var clientY = e.clientY || (e.touches && e.touches[0].clientY);
                    return {
                        x: clientX - rect.left,
                        y: clientY - rect.top
                    };
                }

                function start(e) {
                    drawing = true;
                    isEmpty = false;
                    var pos = getPos(e);
                    ctx.beginPath();
                    ctx.moveTo(pos.x, pos.y);
                    if (e.type.startsWith('touch')) e.preventDefault();
                }

                function move(e) {
                    if (!drawing) return;
                    var pos = getPos(e);
                    ctx.lineTo(pos.x, pos.y);
                    ctx.stroke();
                    if (e.type.startsWith('touch')) e.preventDefault();
                }

                function end() {
                    drawing = false;
                }

                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', move);
                window.addEventListener('mouseup', end);

                canvas.addEventListener('touchstart', start, {
                    passive: false
                });
                canvas.addEventListener('touchmove', move, {
                    passive: false
                });
                window.addEventListener('touchend', end);

                return {
                    clear: function() {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        isEmpty = true;
                    },
                    isEmpty: function() {
                        return isEmpty;
                    },
                    toDataURL: function() {
                        return canvas.toDataURL('image/png');
                    }
                };
            };
        }
    </script>
    </body>

    </html>