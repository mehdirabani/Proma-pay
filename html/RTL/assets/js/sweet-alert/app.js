/**=====================
    Sweet-alert Start
==========================**/

(function () {
  var SweetAlert_custom = {
    init: function () {
      (document.querySelector(".sweet-1").onclick = function () {
        swal({
          title: "نام کاربری خود را ارسال کنید",
        });
      }),
        (document.querySelector(".sweet-2").onclick = function () {
          swal("معجزه است!", "از شما برای بازدید از تم کوبا سپاسگزاریم");
        }),
        (document.querySelector(".sweet-4").onclick = function () {
          swal("لطفا بر روی این دکمه کلیک کنید این برای شما شگفتی بزرگ است.").then(
            (value) => {
              swal(`با تشکر از شما برای بازدید از تم کوبا: ${value}`);
            }
          );
        }),
        (document.querySelector(".sweet-5").onclick = function () {
          swal({
            title: "مطمئنی؟",
            text: "پس از حذف، نمی توانید این فایل خیالی را بازیابی کنید!",
            icon: "warning",
            buttons: true,
            dangerMode: true,
          }).then((willDelete) => {
            if (willDelete) {
              swal("پوف! فایل خیالی شما حذف شد!", {
                icon: "success",
              });
            } else {
              swal("فایل خیالی شما محفوظ است!");
            }
          });
        }),
        (document.querySelector(".sweet-6").onclick = function () {
          swal("آفرین!", "دکمه را زدی!", "warning");
        }),
        (document.querySelector(".sweet-7").onclick = function () {
          swal("It's danger", "دکمه را زدی!", "error");
        }),
        (document.querySelector(".sweet-8").onclick = function () {
          swal("آفرین!", "دکمه را زدی!", "success");
        }),
        (document.querySelector(".sweet-11").onclick = function () {
          swal("دکمه را زدی!", {
            buttons: ["Oh noez!", "Aww yiss!"],
          });
        }),
        (document.querySelector(".sweet-12").onclick = function () {
          swal("یک پیکاچوی وحشی ظاهر شد! میخوای چیکار کنی؟", {
            buttons: {
              cancel: "Run away!",
              catch: {
                text: "Throw Pokeball!",
                value: "catch",
              },
              defeat: true,
            },
          }).then((value) => {
            switch (value) {
              case "defeat":
                swal("پیکاچو بیهوش شد! شما 500 XP به دست آوردید!");
                break;
              case "catch":
                swal("Yeah!", "Pikachu was caught!", "success");
                break;
              default:
                swal("Got away safely!");
            }
          });
        }),
        (document.querySelector(".sweet-13").onclick = function () {
          swal("لطفا! نام کاربری خود را ارسال کنید:", {
            content: "input",
          }).then((value) => {
            swal(`Your name is : ${value}`);
          });
        });
      document.querySelector(".sweet-14").onclick = function () {
        swal("هشدار بسته خودکار!", "فقط یک صبر! 30 ثانیه دیگه میبندم!", {
          buttons: false,
          timer: 4000,
          className: "alert-light-dark",
        });
      };
      document.querySelector(".sweet-15").onclick = function () {
        swal({
          text: 'Search for a movie. e.g. "Herry Poter".',
          content: "input",
          button: {
            text: "Search!",
            closeModal: false,
          },
        })
          .then((name) => {
            if (!name) throw null;

            return fetch(
              `https://itunes.apple.com/search?term=${name}&entity=movie`
            );
          })
          .then((results) => {
            return results.json();
          })
          .then((json) => {
            const movie = json.results[0];

            if (!movie) {
              return swal("No movie was found!");
            }

            const name = movie.trackName;
            const imageURL = movie.artworkUrl100;

            swal({
              title: "Top result:",
              text: name,
              icon: imageURL,
            });
          })
          .catch((err) => {
            if (err) {
              swal("Oh noes!", "The AJAX request failed!", "error");
            } else {
              swal.stopLoading();
              swal.close();
            }
          });
      };
    },
  };

  SweetAlert_custom.init();
})();

/**=====================
  Sweet-alert Ends
==========================**/
