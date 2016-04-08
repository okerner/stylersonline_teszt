var my_app = angular.module('TreeViewApp',['treeControl']);

my_app.controller('TreeViewController', [
  '$scope',
  '$q',
  '$http',
  function(
    $scope,
    $q,
    $http
  ) {
        $scope.binary_tree = {};
        $scope.semafors = {
          show_tree_section : false,
        };

        $scope.tree_options = {
          nodeChildren: "childs",
          dirSelectable: false,
        }
        
        $scope.showSelected = function(sel) {
            $scope.selectedNode = sel;
        };

        var deferred = $q.defer();

        $http.get('/stylersonline_teszt/api.php/get_tree/')
        .then(function (serverData) {
            $scope.binary_tree = serverData.data;
            $scope.semafors.show_tree_section = true;
        }, function( err ) {
            deferred.reject( err );
        });
    }
  ]);