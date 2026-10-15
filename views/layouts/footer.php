<?php
/**
 * views/layouts/footer.php
 * Cierra el layout abierto por layouts/header.php
 */
?>
        </div><!-- /.content-area -->
    </main>
</div><!-- /.app-container -->

<!-- Modal para ver imagen en grande -->
<dialog id="imageModal" class="image-modal">
    <div class="modal-content">
        <button class="modal-close" onclick="document.getElementById('imageModal').close()">&times;</button>
        <img id="modalImage" src="" alt="Imagen ampliada">
    </div>
</dialog>
<!-- Modal genérico de ayuda (compartido por todos los módulos) -->
<dialog id="ayudaModal" class="ayuda-modal">
    <div class="ayuda-modal-header">
        <h2 id="ayudaModalTitle">Ayuda</h2>
        <button type="button" class="ayuda-modal-close" title="Cerrar">&times;</button>
    </div>
    <div class="ayuda-modal-body" id="ayudaModalBody">
        <!-- contenido cargado por AJAX -->
    </div>
</dialog>
<script src="<?= BASE_URL ?>dist/js/jquery-3.7.1.min.js"></script>
<script src="<?= BASE_URL ?>dist/js/main.js"></script>

<?php if (!empty($pageScript)): ?>
    <script src="<?= BASE_URL ?>dist/js/<?= $pageScript ?>"></script>
<?php endif; ?>
<script src="<?= BASE_URL ?>dist/js/ayuda.js"></script>

</body>
</html>