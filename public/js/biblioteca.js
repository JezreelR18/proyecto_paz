document.addEventListener('DOMContentLoaded', function() {
  const modal = document.getElementById('imageModal');
  const modalImage = document.getElementById('modalImage');
  const modalCaption = document.getElementById('modalCaption');
  const closeModal = document.getElementById('closeModal');
  
  // Seleccionar todas las imágenes clickeables (excluyendo el banner)
  const clickableImages = document.querySelectorAll('.bib-item:not(.banner)');
  
  // Abrir modal al hacer click en una imagen
  clickableImages.forEach(item => {
    item.addEventListener('click', function() {
      const img = this.querySelector('img');
      const caption = this.getAttribute('data-caption');
      
      modalImage.src = img.getAttribute('data-src');
      modalImage.alt = img.alt;
      modalCaption.textContent = caption;
      
      modal.showModal();
      document.body.style.overflow = 'hidden'; // Prevenir scroll del body
    });
  });
  
  // Cerrar modal
  closeModal.addEventListener('click', function() {
    modal.close();
    document.body.style.overflow = ''; // Restaurar scroll
  });
  
  // Cerrar modal al hacer click fuera de la imagen
  modal.addEventListener('click', function(event) {
    if (event.target === modal) {
      modal.close();
      document.body.style.overflow = '';
    }
  });
  
  // Cerrar modal con tecla Escape
  document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && modal.open) {
      modal.close();
      document.body.style.overflow = '';
    }
  });
});