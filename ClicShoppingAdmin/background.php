<style>

  body {
    background: url("../images/ClicShoppingAdmin/background_login_admin.png") no-repeat center center / cover fixed,
    linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    margin: 0rem;
    padding: 0rem;
    z-index: 1;
  }

  /* CRITIQUE : Masquer l'ancienne carte SVG */
  #world-map,
  #world-map + svg {
    display: none !important;
    z-index: -100 !important;
  }

  #loginModal {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    z-index: 99999 !important;

    display: flex !important;
    align-items: center !important; /* <-- Centrage vertical */
    justify-content: center !important; /* <-- Centrage horizontal */
    padding: 0 !important;
  }

  #loginModal .modal-dialog {
    /* Augmente la largeur (passé de ~400px à 600px) */
    max-width: 600px !important;
    width: 90% !important; /* Assure une bonne apparence sur mobile */
    margin: 0; /* Important: enlève les marges automatiques qui gènent Flexbox */
  }

  #loginModal .modal-content {
    background-color: #FDFDFD !important;
    border: 1px solid #D1D5DB;
    border-radius: 0.5rem;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
    z-index: 99999 !important;
    position: relative;
    width: 100%; /* S'assure qu'il remplit le modal-dialog */
  }

  #loginModal .modal-body {
    width: auto !important; /* Laisse le conteneur gérer la largeur */
    padding-top: 1.5rem !important; /* Ajuste le padding pour un look propre */
  }
</style>