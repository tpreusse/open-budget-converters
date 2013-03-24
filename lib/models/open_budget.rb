module OpenBudget
  class Node
    attr_accessor :id, :name, :gross_cost, :revenue, :children

    def initialize
      @gross_cost = {}
      @revenue = {}
      @children = []
    end

    def as_json(options = nil)
      json = {
        id: id,
        name: name
      }
      json[:gross_cost] = gross_cost unless gross_cost.keys.empty?
      json[:revenue] = revenue unless revenue.keys.empty?
      json[:children] = children unless children.empty?
      json
    end

    def add_gross_cost(type, year, balance)
      @gross_cost[type] ||= {}
      @gross_cost[type][year] ||= 0
      @gross_cost[type][year] = (@gross_cost[type][year] + balance).round(2)
    end

    def add_revenue(type, year, balance)
      @revenue[type] ||= {}
      @revenue[type][year] ||= 0
      @revenue[type][year] = (@revenue[type][year] + balance).round(2)
    end
  end

  class Budget
    attr_accessor :nodes, :meta

    def initialize
      @nodes = []
      @node_index = {}
    end

    def get_node id_path
      id = id_path.join '_'
      @node_index[id] ||= lambda do
        node = Node.new
        node.id = id
        # add to collection
        if id_path.length > 1
          parent = get_node id_path.take(id_path.length - 1)
          parent.children
        else
          @nodes
        end << node

        node
      end.call
    end

    def as_json(options = nil)
      {
        meta: meta,
        nodes: nodes
      }
    end

    def from_bfh_csv file_path
      CSV.foreach(file_path, :encoding => 'windows-1251:utf-8', :headers => true, :header_converters => :symbol, :converters => :all) do |row|
        if !@meta
          @meta = {
            name: row[:gemeinde],
            bfs_no: row[:bfsnr]
          }
        end

        account_type = row[:kontenbereich_nummer].to_i
        if [3, 4, 5, 6].include? account_type

          node = get_node [row[:aufgabenbereich_nummer], row[:aufgabe_nummer], row[:aufgabenstelle_nummer]].reject(&:blank?)
          node.name = [row[:aufgabenbereich_name], row[:aufgabe_name], row[:aufgabenstelle_name]].reject(&:blank?).last
          # ToDo: seperate "investitionsrechnung" [5, 6] and "laufende rechnung" [3, 4]
          if [3, 5].include? account_type
            node.add_gross_cost('accounts', row[:jahr], row[:saldo])
          elsif [4, 6].include? account_type
            node.add_revenue('accounts', row[:jahr], row[:saldo])
          end
        end
      end
    end
  end
end
