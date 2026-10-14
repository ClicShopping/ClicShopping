document.addEventListener("DOMContentLoaded", function () {
  function getVotedReviews() {
    const votedReviews = localStorage.getItem('votedReviews');
    return votedReviews ? JSON.parse(votedReviews) : [];
  }

  function hasUserVoted(reviewId) {
    return getVotedReviews().includes(reviewId);
  }

  function markReviewAsVoted(reviewId) {
    const votedReviews = getVotedReviews();
    if (!votedReviews.includes(reviewId)) {
      votedReviews.push(reviewId);
      localStorage.setItem('votedReviews', JSON.stringify(votedReviews));
    }
  }

  function updateVoteCounts(response) {
    //  Serveru update
    if (response.yesCount !== undefined) {
      const yesValue = document.querySelector('.yesValue');
      if (yesValue) yesValue.textContent = '(' + response.yesCount + ')';
    }
    if (response.noCount !== undefined) {
      const noValue = document.querySelector('.noValue');
      if (noValue) noValue.textContent = '(' + response.noCount + ')';
    }
  }

  function saveVoteToServer(reviewId, vote, productId, customerId, ajaxUrl) {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", ajaxUrl, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4) {
        if (xhr.status === 200) {
          try {
            const response = JSON.parse(xhr.responseText);
            updateVoteCounts(response);
            console.log('Vote enregistré et compteurs mis à jour pour review ' + reviewId);
          } catch (e) {
            console.error('Erreur parsing JSON:', e);
            console.log('Réponse brute:', xhr.responseText);
          }
        } else {
          console.error('Erreur serveur vote : ' + xhr.statusText);
        }
      }
    };
    xhr.send("reviewId=" + reviewId + "&vote=" + vote + "&product_id=" + productId + "&customer_id=" + customerId);
  }

  document.querySelectorAll('.yesButton, .noButton').forEach(function (button) {
    button.addEventListener("click", function () {
      const reviewId = this.getAttribute('data-unique-id');
      const productId = this.getAttribute('data-product-id');
      const customerId = this.getAttribute('data-customer-id');
      const ajaxUrl = this.dataset.ajaxUrl;

      if (!reviewId || !productId || !ajaxUrl) {
        console.error('Attributs manquants sur le bouton.');
        return;
      }

      if (hasUserVoted(reviewId)) return;

      const parentDiv = this.closest(".moduleProductsInfoReviewCustomersNotice");
      const yesButton = parentDiv.querySelector(".yesButton");
      const noButton = parentDiv.querySelector(".noButton");
      const yesValue = parentDiv.querySelector(".yesValue");
      const noValue = parentDiv.querySelector(".noValue");
      const thankYouMessage = parentDiv.querySelector(".thankYouMessage");

      // Hide buttomn but keep the visible value for update
      yesButton.style.display = "none";
      noButton.style.display = "none";
      thankYouMessage.style.display = "inline";

      markReviewAsVoted(reviewId);
      const vote = this.classList.contains("yesButton") ? 1 : 0;
      saveVoteToServer(reviewId, vote, productId, customerId, ajaxUrl);
    });
  });
});


