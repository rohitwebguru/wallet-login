jQuery(document).ready(function () {
  const disconnectButton = jQuery(".dropbtn");
  const disconnectDropdownContent = jQuery(".dropdown-content");
  let connectButtons = jQuery(".btn-login");
  let menuConnectButton = jQuery(".menu-btn-login");
  const dropdown = jQuery(".dropdown");
  
  const modal = jQuery('.modal');
  const overlay = jQuery('.overlay');
  const btnCloseModal = jQuery('.close-modal');
  const metamaskClick = jQuery(".metamask_click");
  const coinbaseClick = jQuery(".coinbase_click");
  let iteration = null;
  let provider = null;

  const openModal = function () {
    modal.removeClass('hidden');
    overlay.removeClass('hidden');
  };

  const closeModal = function () {
    modal.addClass('hidden');
    overlay.addClass('hidden');
  };

  metamaskClick.on("click", async function (e) {
      await e.preventDefault();
      await connectWallet("metamask");
  })

  coinbaseClick.on("click", async function (e) {
    if (window.ethereum !== undefined) {
      await e.preventDefault();
      await connectWallet("coinbase");
    } else {
      alert('Coinbase extension is not installed!');
    }
  })

  if (connectButtons.length > 0) {
    let connectButtonContainer = connectButtons.eq(0);
    connectButtonContainer.on("click", openModal);
    modalOpenBlock();
  }

  if (menuConnectButton.length > 0) {    
    let menuButtonContainer = menuConnectButton.eq(0);
    menuButtonContainer.on("click", openModal);
    modalOpenBlock();
  }

  function modalOpenBlock() {
    btnCloseModal.on('click', closeModal);
    overlay.on('click', closeModal);
    jQuery(document).on('keydown', function (e) {
      if (e.key === 'Escape' && !modal.hasClass('hidden')) {
        closeModal();
      }
    });
  }

  function closeDropdown() {
    disconnectDropdownContent.removeClass("show");
  }

  if (disconnectButton) {
    disconnectButton.on("click", function () {
      disconnectDropdownContent.toggleClass("show");
    });
  }

  jQuery(document).on("click", function (event) {
    const target = event.target;
    if (!jQuery(target).closest(".dropdown").length) {
      closeDropdown();
    }
  });

  async function connectWallet(option=null) {
    if (typeof window.ethereum !== undefined) {
      try {
        let accounts;
        if(option === "metamask"){
          if (typeof window.ethereum !== "undefined") {
            provider = window.ethereum;
            if (window.ethereum.providers?.length) {
              window.ethereum.providers.forEach(async (p,i) => {
                if (p.isMetaMask) {provider = p; iteration = i;}
              });
              accounts = await provider.request({
                method: "eth_requestAccounts",
                params: [],
              });
              localStorage.setItem("wprovider","metamask");
            }else{
              provider = window.ethereum;
              accounts = await window.ethereum.request({
                method: "eth_requestAccounts",
              });
              localStorage.setItem("wprovider","metamask");
            }
          }
        }else if(option == "coinbase"){
          if (typeof window.ethereum !== "undefined" && window.ethereum.providers != null) {
            provider = window.ethereum;
            if (window.ethereum.providers?.length) {
              window.ethereum.providers.forEach(async (p,i) => {
                if (p.isCoinbaseWallet) {provider = p; iteration = i;}
              });
            }
            accounts = await provider.request({
              method: "eth_requestAccounts",
              params: [],
            });
            localStorage.setItem("wprovider","coinbase");
          }else{
            alert("No CoinbaseWallet Extension detected");
          }
        }
        if (accounts && accounts.length > 0) {
          const connectedAddress = accounts[0];
          await localStorage.setItem("walletaddress", connectedAddress);
          var partialAddress = connectedAddress.substr(
            connectedAddress.length - 11
          );
          var final_address = "0x..." + partialAddress;
          var domain = customData.websiteURL.replace(/^https?:\/\//, '').replace(/\/$/, '');
          const payload = {
            ethereum_wallet_address: connectedAddress,
            ethereum_wallet_email: connectedAddress + '@' + domain,
          };
          var register_url = customData.websiteURL + '/wp-json/custom/v1/register';

          jQuery.ajax({
            url: register_url,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            dataType: 'json',
            success: function (data) {
              var login_url = jQuery("#login-url").val();
              if (login_url !== '' && login_url !== undefined) {
                window.location.href = login_url;
              }
            },
            error: function (error) {
              console.error("Failed to register user:", error);
            }
          }).then(function () {
            if (!modal.hasClass("hidden") || !overlay.hasClass("hidden")) {
              modal.addClass("hidden");
              overlay.addClass("hidden");
            }
          })

          if (connectButtons.length > 0) {
            const connectButtonContainer = connectButtons.eq(0);
            connectButtonContainer.css("display", "none");
            dropdown.css("display", "block");
            var fillAddresses = jQuery(".dropbtn span");

            if (window.ethereum.providers!= null && window.ethereum.providers[iteration].selectedAddress) {
              onAccountChange();
              fillAddresses.text(final_address);
              localStorage.setItem("connectedAddress", final_address);
            }else if(window.ethereum.selectedAddress){
              onAccountChange();
              fillAddresses.text(final_address);
              localStorage.setItem("connectedAddress", final_address);
            } else {
              localStorage.clear()
            }
          }

          if (menuConnectButton.length > 0) {
            const connectButtonContainer = menuConnectButton.eq(0);
            connectButtonContainer.css("display", "none");
            dropdown.css("display", "block");
            var fillAddresses = jQuery(".dropbtn span");

            if (window.ethereum.providers[iteration].selectedAddress || window.ethereum.selectedAddress) {
              onAccountChange();
              fillAddresses.text(final_address);
              localStorage.setItem("connectedAddress", final_address);
            } else {
              localStorage.clear()
            }
          }

        }
      } catch (error) {
        console.error("Failed to connect to MetaMask:", error.message);
      }
    } else {
      console.error(
        "MetaMask not detected. Please install the MetaMask extension."
      );
    }
  }

  function checkConnectedWallet() {
    var wprovider = localStorage.getItem("wprovider");

    if( wprovider == 'coinbase'){
      if( window.ethereum.selectedProvider !== null ){

        if( window.ethereum.selectedProvider.isCoinbaseWallet !== true){
          disconnectWallet();
        }
      }else{
        disconnectWallet();
      }
    }

    const connectedAddress = localStorage.getItem("connectedAddress");
    const connectButtons = jQuery(".btn-login");

    if (connectedAddress) {
      if (connectButtons.length > 0) {
        const connectButtonContainer = connectButtons.eq(0);
        connectButtonContainer.css('display', 'none');
        dropdown.css('display', 'block');
        var fillAddresses = jQuery(".dropbtn span");
        fillAddresses.text(connectedAddress);
      }

      if (menuConnectButton.length > 0) {
        const menuButtonContainer = menuConnectButton.eq(0);
        menuButtonContainer.css('display', 'none');
        dropdown.css('display', 'block');
        var fillAddresses = jQuery(".dropbtn span");
        fillAddresses.text(connectedAddress);
      }
    } else {
      if (connectButtons.length > 0) {
        const connectButtonContainer = connectButtons.eq(0);
        const dropdown = jQuery(".dropdown");
        dropdown.css('display', 'none');
        connectButtonContainer.css('display', 'flex');
      }
      if (menuConnectButton.length > 0) {
        const menuButtonContainer = menuConnectButton.eq(0);
        const dropdown = jQuery(".dropdown");
        dropdown.css('display', 'none');
        menuButtonContainer.css('display', 'flex');
      }
    }
  }

  jQuery(document).ready(async function () {
    await checkConnectedWallet();
    onAccountChange();
  });

  function onAccountChange(){
    var wprovider = localStorage.getItem("wprovider");

    if (window.ethereum) {
      if(wprovider == "metamask"){
        provider = window.ethereum;
        if (window.ethereum.providers?.length) {
          window.ethereum.providers.forEach(async (p,i) => {
            if (p.isMetaMask) {provider = p; iteration = i;}
          });
        }
      }else if(wprovider == "coinbase"){
        provider = window.ethereum;
        if (window.ethereum.providers?.length) {
          window.ethereum.providers.forEach(async (p,i) => {
            if (p.isCoinbaseWallet) {provider = p; iteration = i;}
          });
        }
      }
      if(provider != null && provider != undefined && window.ethereum.providers!= null){
        provider.on("accountsChanged", function (accounts) {
          if (window.ethereum.providers[iteration].selectedAddress) {
            connectWallet(wprovider);
          }

          var walletaddress = localStorage.getItem("walletaddress");
          var is_connected = accounts.includes(walletaddress);

          if ((walletaddress != null) && (!is_connected)) {
            disconnectWallet();
          }

          if (!modal.hasClass("hidden") || !overlay.hasClass("hidden")) {
            modal.addClass("hidden");
            overlay.addClass("hidden");
          }
        });
      }else{
        window.ethereum.on("accountsChanged", function (accounts) {
          if (window.ethereum.selectedAddress) {
            connectWallet(wprovider);
          }

          var walletaddress = localStorage.getItem("walletaddress");
          var is_connected = accounts.includes(walletaddress);

          if ((walletaddress != null) && (!is_connected)) {
            disconnectWallet();
          }
        });
      }
    }
  }

  function disconnectWallet() {
    localStorage.removeItem('connectedAddress');
    localStorage.removeItem('walletAddress');
    localStorage.removeItem('wprovider');
    localStorage.clear();
    connectedAddress = "";

    var logout_url = customData.websiteURL + '/wp-json/custom/v1/logout';

    try {
      jQuery.ajax({
        url: logout_url,
        method: 'POST',
        contentType: 'application/json',
        success: function () {
          var logout_url = jQuery("#logout-url").val();
          window.location.href = logout_url;
        },
        error: function (error) {
          console.error("Failed to log out:", error);
        }
      });

    } catch (error) {
      console.error("Failed to log out:", error);
    }

    const connectButtons = jQuery(".btn-login");
    if (connectButtons.length > 0) {

      const connectButtonContainer = connectButtons.eq(0);
      const dropdown = jQuery(".dropdown");
      dropdown.css('display', 'none');
      connectButtonContainer.css('display', 'flex');
    }

  }

  jQuery(".dropdown ul li:last-child a").on("click", function (event) {
    event.preventDefault();
    disconnectWallet();
  });

})
