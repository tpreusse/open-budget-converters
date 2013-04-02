# encoding: UTF-8

module OpenBudget
  class BalanceCollection
    attr_reader :accounts

    def initialize
      @accounts = {}
    end

    def add(type, year, balance)
      @accounts[type] ||= {}
      @accounts[type][year] ||= 0
      @accounts[type][year] = (@accounts[type][year] + balance).round(2)
    end

    def +(b)
      collection = BalanceCollection.new
      [accounts, b.accounts].each do |accounts|
        accounts.each do |type, years|
          years.each do |year, balance|
            collection.add(type, year, balance)
          end
        end
      end
      collection
    end

    def ==(b)
      @accounts == b.accounts
    end

    def empty?
      @accounts.keys.empty?
    end

    def as_json(options = nil)
      @accounts
    end
  end

  class Node
    attr_accessor :id, :name, :children, :parent
    attr_reader :gross_cost, :revenue

    def initialize
      @gross_cost = BalanceCollection.new
      @revenue = BalanceCollection.new
      @children = []
    end

    def touch
      @dirty = true
      if parent
        parent.touch
      end
    end

    def name=(value)
      if @name.present? && @name.downcase != value.downcase
        warn "name diff detected #{value} for #{@name} (#{id})"
      end
      @name = value
    end

    def prepare
      if @dirty
        puts "cleaning #{id}"
        if @children.empty?
          puts "leaf node was dirty"
        else
          sum_gross_cost = BalanceCollection.new
          sum_revenue = BalanceCollection.new
          children.each do |child|
            child.prepare
            sum_gross_cost += child.gross_cost
            sum_revenue += child.revenue
          end
          if sum_gross_cost != gross_cost
            warn "gross_cost diff detected #{id} sum #{sum_gross_cost.accounts.to_s} orig #{gross_cost.accounts.to_s}"
            @gross_cost = sum_gross_cost
          end
          if sum_revenue != revenue
            warn "revenue diff detected #{id} sum #{sum_revenue.accounts.to_s} orig #{revenue.accounts.to_s}"
            @revenue = sum_revenue
          end
        end

        @dirty = false
      end
    end

    def as_json(options = nil)
      json = {
        id: id,
        name: name
      }
      prepare
      json[:gross_cost] = gross_cost unless gross_cost.empty?
      json[:revenue] = revenue unless revenue.empty?
      json[:children] = children unless children.empty?
      json
    end

    def add_gross_cost(type, year, balance)
      if balance < 0
        add_revenue(type, year, balance * -1)
        # parent.touch # disabled propagating, needs more thought
      elsif balance > 0
        @gross_cost.add(type, year, balance)
      end
    end

    def add_revenue(type, year, balance)
      if balance < 0
        add_gross_cost(type, year, balance * -1)
        # parent.touch # disabled propagating, needs more thought
      elsif balance > 0
        @revenue.add(type, year, balance)
      end
    end
  end

  class Budget
    attr_accessor :nodes, :meta

    def initialize
      @nodes = []
      @node_index = {}
    end

    def get_node id_path, names
      id = id_path.join '_'
      node = @node_index[id] ||= lambda do
        node = Node.new
        node.id = id

        # add to collection
        if id_path.length > 1
          parent = get_node id_path.take(id_path.length - 1), names.take(names.length - 1)
          node.parent = parent
          parent.children
        else
          node.touch
          @nodes
        end << node

        node
      end.call

      name = names[id_path.length - 1].to_s.strip
      if name.blank?
        puts "blank name id_path #{id_path.to_s} names #{names.to_s}"
      end
      node.name = name
      node
    end

    def as_json(options = nil)
      {
        meta: meta,
        data: nodes
      }
    end

    def load_meta file_path
      @meta = JSON.parse File.read(file_path)
    end

    def from_zurich_csv file_path
      # :encoding => 'windows-1251:utf-8', 
      CSV.foreach(file_path, :col_sep => ';', :headers => true, :header_converters => :symbol, :converters => :all) do |row|
        
        # p row

        row[:bezeichnung].strip!
        code = row[:code].to_s
        konto = row[:konto].to_s.strip
        account_type = konto[0].to_i
        # if [3, 4, 5, 6].include? account_type

        dienstabteilung = row[:dienstabteilung]
        if dienstabteilung == 'Kultur' && /^15[0-9]{2}$/.match(code)
          code = '1501'
        elsif dienstabteilung == 'Museum Rietberg' && /^152[0-4]$/.match(code)
          code = '1520'
        end

        id_path = [
          code[0..1], # Departement
          code, # [2..3] = Dienstabteilung
          # konto[0..1], # seperate by account purpose
          konto[0..-1] # account # minus account_type
        ].each(&:strip).reject(&:blank?)
        names = [
          row[:departement],
          dienstabteilung,
          # '-',
          row[:bezeichnung]
        ]

        node = get_node id_path, names

        # ToDo: seperate "investitionsrechnung" [5, 6] and "laufende rechnung" [3, 4]


        method = nil
        case row[:bezeichnung]
        # overview row
        when 'Aufwand'#, 'Ausgaben'
          method = :add_gross_cost
        when 'Ertrag'#, 'Einnahmen'
          method = :add_revenue
        # detail row
        else
          if konto.present?
            case account_type
            when 3#, 5 # Aufwand, Ausgaben
              method = :add_gross_cost
            when 4#, 6 # Ertrag, Einnahmen
              method = :add_revenue
            end
          end
        end

        values = [row[:budget_2012_fr], row[:budget_2013_fr], row[:rechnung_2011_fr]].collect do |value|
          value.to_s.gsub('’','').to_f
        end
        case method
        when :add_revenue
          values.collect! do |value|
            value * -1
          end
        end
        if method
          node.method(method).call('budgets', 2012, values[0])
          node.method(method).call('budgets', 2013, values[1])
          # node.method(method).call('accounts', 2011, values[2])
        end

        # budget_2013_fr bezeichnung_sofern_gemss_art_4_fvo_erforderlich:nil
        
        # if [3, 5].include? account_type
        #   node.add_gross_cost('accounts', row[:jahr], row[:saldo])
        # elsif [4, 6].include? account_type
        #   node.add_revenue('accounts', row[:jahr], row[:saldo])
        # end
        # end
      end
    end
  end
end
